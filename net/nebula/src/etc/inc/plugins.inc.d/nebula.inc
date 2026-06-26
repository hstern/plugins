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

function nebula_enabled()
{
    return (string)(new \OPNsense\Nebula\Nebula())->general->enabled === '1';
}

function nebula_services()
{
    if (!nebula_enabled()) {
        return [];
    }

    return [[
        'description' => gettext('Nebula'),
        'configd' => [
            'start' => ['nebula start'],
            'restart' => ['nebula restart'],
            'stop' => ['nebula stop'],
        ],
        'pidfile' => '/var/run/nebula.pid',
        'name' => 'nebula',
    ]];
}

function nebula_syslog()
{
    return ['nebula' => ['facility' => ['nebula']]];
}

function nebula_configure()
{
    return ['vpn' => ['nebula_configure_do:2']];
}

function nebula_configure_do($verbose = false)
{
    if (!nebula_enabled()) {
        return;
    }
    service_log('Configuring Nebula overlay...', $verbose);
    configd_run('nebula start');
    service_log("done.\n", $verbose);
}
