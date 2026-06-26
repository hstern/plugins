{#
 # Copyright (c) 2026 Henry Stern <henry@stern.ca>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
#}

<script>
    'use strict';

    function nebulaUpdateStatus() {
        ajaxCall('/api/nebula/service/status', {}, function (data) {
            var status = (data && data.status) || 'unknown';
            var map = {
                'running':  ['label-success', '{{ lang._('running') }}'],
                'stopped':  ['label-danger',  '{{ lang._('stopped') }}'],
                'disabled': ['label-default', '{{ lang._('disabled') }}']
            };
            var view = map[status] || ['label-default', status];
            $('#nebula_status').attr('class', 'label ' + view[0]).text(view[1]);
        });
    }

    $(document).ready(function () {
        mapDataToFormUI({'frm_general': '/api/nebula/settings/get'}).done(function () {
            nebulaUpdateStatus();
        });

        $('#saveAct').click(function () {
            saveFormToEndpoint('/api/nebula/settings/set', 'frm_general', function () {
                ajaxCall('/api/nebula/service/reconfigure', {}, function () {
                    nebulaUpdateStatus();
                });
            }, true);
        });

        $('#startAct').click(function () {
            ajaxCall('/api/nebula/service/start', {}, nebulaUpdateStatus);
        });
        $('#stopAct').click(function () {
            ajaxCall('/api/nebula/service/stop', {}, nebulaUpdateStatus);
        });
        $('#restartAct').click(function () {
            ajaxCall('/api/nebula/service/restart', {}, nebulaUpdateStatus);
        });
    });
</script>

<div class="content-box">
    {{ partial("layout_partials/base_form", ['fields': generalForm, 'id': 'frm_general']) }}
    <div class="col-md-12" style="margin: 1em 0;">
        <strong>{{ lang._('Service') }}:</strong>
        <span id="nebula_status" class="label label-default">{{ lang._('checking...') }}</span>
        &nbsp;
        <button class="btn btn-default" id="startAct" type="button">{{ lang._('Start') }}</button>
        <button class="btn btn-default" id="stopAct" type="button">{{ lang._('Stop') }}</button>
        <button class="btn btn-default" id="restartAct" type="button">{{ lang._('Restart') }}</button>
        <button class="btn btn-primary" id="saveAct" type="button">{{ lang._('Save & Apply') }}</button>
    </div>
</div>
