#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once 'config.inc';
require_once 'util.inc';

use OPNsense\Nebula\Nebula;

const NEBULA_BIN = '/usr/local/bin/nebula';
const DAEMON_BIN = '/usr/sbin/daemon';

/**
 * Return the live daemon pid, or null if not running (clears a stale pidfile).
 */
function nebula_running_pid()
{
    if (!is_file(Nebula::PID_FILE)) {
        return null;
    }
    /* CLI php has no posix extension here; probe liveness with `kill -0`. */
    $pid = (int)trim(@file_get_contents(Nebula::PID_FILE));
    if ($pid > 0) {
        exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
        if ($rc === 0) {
            return $pid;
        }
    }
    @unlink(Nebula::PID_FILE);
    return null;
}

/**
 * Write the CA/cert/key material and the rendered config to disk (mode 0600).
 * Returns false when any certificate field is empty.
 */
function nebula_write_files($model)
{
    $g = $model->general;
    $ca = trim((string)$g->ca);
    $cert = trim((string)$g->cert);
    $key = trim((string)$g->key);
    if ($ca === '' || $cert === '' || $key === '') {
        return false;
    }
    if (!is_dir(Nebula::NEBULA_DIR)) {
        mkdir(Nebula::NEBULA_DIR, 0700, true);
    }
    /* create cert/key/config restricted from birth — no world-readable window */
    $oldUmask = umask(0077);
    file_put_contents(Nebula::CA_FILE, $ca . "\n");
    file_put_contents(Nebula::CERT_FILE, $cert . "\n");
    file_put_contents(Nebula::KEY_FILE, $key . "\n");
    file_put_contents(Nebula::CONFIG_FILE, $model->generateConfig());
    umask($oldUmask);
    chmod(Nebula::CA_FILE, 0600);
    chmod(Nebula::CERT_FILE, 0600);
    chmod(Nebula::KEY_FILE, 0600);
    chmod(Nebula::CONFIG_FILE, 0600);
    return true;
}

/**
 * Validate the rendered config with `nebula -test`.
 */
function nebula_validate()
{
    $cmd = sprintf('%s -test -config %s 2>&1', NEBULA_BIN, escapeshellarg(Nebula::CONFIG_FILE));
    exec($cmd, $output, $rc);
    return $rc === 0;
}

/**
 * Start the daemon (no-op if already running). Renders config first and
 * verifies the launch took by polling the pidfile.
 */
function nebula_start($model)
{
    if (nebula_running_pid() !== null) {
        return;
    }
    if (!nebula_write_files($model)) {
        syslog(LOG_ERR, 'nebula: not starting — missing certificate material');
        fwrite(STDERR, "nebula: missing certificate material; not starting\n");
        return;
    }
    if (!nebula_validate()) {
        syslog(LOG_ERR, 'nebula: not starting — invalid configuration');
        fwrite(STDERR, "nebula: invalid configuration (failed nebula -test); not starting\n");
        return;
    }
    /* -S -T nebula routes nebula's output to syslog under the "nebula" tag.
     * The </dev/null >/dev/null 2>&1 detaches daemon(8) from the configd output
     * pipe it inherits here; without it the long-lived supervisor holds that
     * pipe open and configctl blocks until the configd timeout on every start. */
    $cmd = sprintf(
        '%s -S -T nebula -p %s %s -config %s </dev/null >/dev/null 2>&1',
        DAEMON_BIN,
        escapeshellarg(Nebula::PID_FILE),
        NEBULA_BIN,
        escapeshellarg(Nebula::CONFIG_FILE)
    );
    exec($cmd);

    for ($i = 0; $i < 8; $i++) {
        usleep(200 * 1000);
        if (nebula_running_pid() !== null) {
            syslog(LOG_NOTICE, 'nebula: started');
            return;
        }
    }
    syslog(LOG_ERR, 'nebula: daemon failed to start');
    fwrite(STDERR, "nebula: daemon failed to start (see the nebula log)\n");
    @unlink(Nebula::PID_FILE);
}

/**
 * Stop the daemon if running.
 */
function nebula_stop()
{
    $pid = nebula_running_pid();
    if ($pid === null) {
        return;
    }
    exec(sprintf('/bin/kill -TERM %d 2>/dev/null', $pid));
    for ($i = 0; $i < 10; $i++) {
        usleep(200 * 1000);
        exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
        if ($rc !== 0) {
            break;
        }
    }
    /* still alive after the grace period — force it down */
    exec(sprintf('/bin/kill -0 %d 2>/dev/null', $pid), $out, $rc);
    if ($rc === 0) {
        exec(sprintf('/bin/kill -KILL %d 2>/dev/null', $pid));
    }
    @unlink(Nebula::PID_FILE);
    syslog(LOG_NOTICE, 'nebula: stopped');
}

/**
 * Print a one-line status the service controller greps.
 */
function nebula_status($model)
{
    if ((string)$model->general->enabled !== '1') {
        echo "nebula is disabled\n";
    } elseif (nebula_running_pid() !== null) {
        echo "nebula is running\n";
    } else {
        echo "nebula is not running\n";
    }
}

$action = $argv[1] ?? '';
$model = new Nebula();

switch ($action) {
    case 'start':
        nebula_start($model);
        break;
    case 'stop':
        nebula_stop();
        break;
    case 'restart':
    case 'apply':
        nebula_stop();
        nebula_start($model);
        break;
    case 'status':
        nebula_status($model);
        break;
    default:
        fwrite(STDERR, "Usage: setup.php [start|stop|restart|apply|status]\n");
        exit(1);
}
