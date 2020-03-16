$(document).ready(function() {

    if (OCA.Files && OCA.Files.fileActions) {

        function openDirectory(file, data) {
            var dir;
            if (data.dir === '/'){
                dir = data.dir+file;
            }
            else{
                dir = data.dir+'/'+file;
            }
            var token = $('#sharingToken').val();
            // user is connected
            if (!token){
                var url = OC.generateUrl('apps/gpxpod/?dir={dir}',{'dir': dir});
            }
            // we are browsing a shared directory
            else{
                var url = OC.generateUrl('apps/gpxpod/publicFolder?token={token}&path={path}',
                    {'token': token, 'path': dir});
            }
            window.open(url, '_blank');
        }

        function addDirectoryOpenDirectory(file, data) {
            var token = $('#sharingToken').val();
            // user is not connected
            if (token) {
                openDirectory(file, data);
            }

            var path;
            if (data.dir === '/'){
                path = data.dir+file;
            }
            else{
                path = data.dir+'/'+file;
            }
            var req = {
                path: path
            };
            var url = OC.generateUrl('/apps/gpxpod/adddirectory');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                OC.Notification.showTemporary(
                    t('gpxpod', 'Directory {p} has been added', {p: path})
                );
            }).fail(function(response) {
                // well, no need to tell the user
                //OC.Notification.showTemporary(
                //    t('gpxpod', 'Failed to add directory') + '. ' + response.responseText
                //);
                console.log(t('gpxpod', 'Failed to add directory') + '. ' + response.responseText);
            }).always(function() {
                openDirectory(file, data);
            });
        }

        // file action for directories
        OCA.Files.fileActions.registerAction({
            name: 'viewDirectoryGpxPod',
            displayName: t('gpxpod','View in GpxPod'),
            mime: 'httpd/unix-directory',
            permissions: OC.PERMISSION_READ,
            iconClass: 'icon-gpxpod-black',
            actionHandler: function(file, data) {
                addDirectoryOpenDirectory(file, data);
            }
        });

        function openFile(file, data) {
            var token = $('#sharingToken').val();
            // if we are logged
            if (!token){
                var url = OC.generateUrl('apps/gpxpod/?dir={dir}&file={file}', {'dir': data.dir, 'file': file});
            }
            // if we are in share browsing
            else{
                var url = OC.generateUrl('apps/gpxpod/publicFile?token={token}&path={path}&filename={filename}',
                        {'token': token, 'path': data.dir, 'filename': file});
            }
            window.open(url, '_blank');
        }

        function addDirectoryOpenFile(file, data) {
            var path = data.dir;
            if (path === '') {
                path = '/';
            }
            var req = {
                path: path
            };
            var url = OC.generateUrl('/apps/gpxpod/adddirectory');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true
            }).done(function (response) {
                OC.Notification.showTemporary(
                    t('gpxpod', 'Directory {p} has been added', {p: path})
                );
            }).fail(function(response) {
                // well, no need to tell the user
                //OC.Notification.showTemporary(
                //    t('gpxpod', 'Failed to add directory') + '. ' + response.responseText
                //);
                console.log(t('gpxpod', 'Failed to add directory') + '. ' + response.responseText);
            }).always(function() {
                openFile(file, data);
            });
        }

        OCA.Files.fileActions.registerAction({
            name: 'viewFileGpxPod',
            displayName: t('gpxpod', 'View in GpxPod'),
            mime: 'application/gpx+xml',
            permissions: OC.PERMISSION_READ,
            iconClass: 'icon-gpxpod-black',
            actionHandler: function(file, data) {
                addDirectoryOpenFile(file, data);
            }
        });

        // default action is set only for logged in users
        if (!$('#sharingToken').val()){
            OCA.Files.fileActions.register(
                'application/gpx+xml',
                'viewFileGpxPodDefault',
                OC.PERMISSION_READ,
                '',
                function(file, data) {
                    addDirectoryOpenFile(file, data);
                }
            );
            OCA.Files.fileActions.setDefault('application/gpx+xml', 'viewFileGpxPodDefault');
        }
    }

});

