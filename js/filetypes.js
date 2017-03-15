$(document).ready(function() {

    if (OCA.Files && OCA.Files.fileActions) {

        OCA.Files.fileActions.registerAction({
            name: 'viewDirectoryGpxPod',
            displayName: t('gpxpod','View in GpxPod'),
            mime: 'httpd/unix-directory',
            permissions: OC.PERMISSION_READ,
            icon: function () {return OC.imagePath('gpxpod', 'app_black');},
            actionHandler: function(file, data){
                var dir = data.dir+'/'+file;
                var url = OC.generateUrl('apps/gpxpod/?dir={dir}',{'dir': dir});
                window.open(url, '_blank');
            }
        });

        function openFile(file, data){
            var url = OC.generateUrl('apps/gpxpod/?dir={dir}&file={file}',{'dir': data.dir, 'file': file});
            window.open(url, '_blank');
        }

        OCA.Files.fileActions.registerAction({
            name: 'viewFileGpxPod',
            displayName: t('gpxpod', 'View in GpxPod'),
            mime: 'application/gpx+xml',
            permissions: OC.PERMISSION_READ,
            icon: function () {return OC.imagePath('gpxpod', 'app_black');},
            actionHandler: openFile
        });

        OCA.Files.fileActions.register('application/gpx+xml', 'viewFileGpxPodDefault', OC.PERMISSION_READ, '', openFile);
        OCA.Files.fileActions.setDefault('application/gpx+xml', 'viewFileGpxPodDefault');
    }

});

