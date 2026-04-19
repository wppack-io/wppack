/**
 * WPPack S3 Direct Upload Interceptor
 *
 * Replaces WordPress default media uploads with S3 direct uploads
 * via pre-signed URLs.
 */
(function () {
    'use strict';

    if (typeof wp === 'undefined' || typeof wp.Uploader === 'undefined') {
        return;
    }

    var config = window.wppS3Upload || {};
    var originalInit = wp.Uploader.prototype.init;

    wp.Uploader.prototype.init = function () {
        originalInit.apply(this, arguments);

        var uploader = this.uploader;
        if (!uploader) {
            return;
        }

        uploader.unbindAll('BeforeUpload');

        uploader.bind('BeforeUpload', function (up, file) {
            up.stop();
            uploadToS3(up, file);
        });
    };

    function uploadToS3(up, file) {
        var nativeFile = file.getNative();
        if (!nativeFile) {
            triggerError(up, file, 'Could not access the native file object.');
            return;
        }

        getPresignedUrl(nativeFile)
            .then(function (presigned) {
                return putToS3(up, file, nativeFile, presigned);
            })
            .then(function (presigned) {
                return registerAttachment(presigned.key);
            })
            .then(function (attachment) {
                completeUpload(up, file, attachment);
            })
            .catch(function (err) {
                triggerError(up, file, err.message || 'Upload failed.');
            });
    }

    function getPresignedUrl(nativeFile) {
        return fetch(config.presignedUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                filename: nativeFile.name,
                content_type: nativeFile.type || 'application/octet-stream',
                content_length: String(nativeFile.size)
            })
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.error || 'Failed to get pre-signed URL.');
                }
                return data;
            });
        });
    }

    function putToS3(up, file, nativeFile, presigned) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    file.loaded = e.loaded;
                    file.percent = Math.min(
                        Math.ceil((e.loaded / e.total) * 100),
                        100
                    );
                    up.trigger('UploadProgress', file);
                }
            });

            xhr.addEventListener('load', function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(presigned);
                } else {
                    reject(new Error('S3 upload failed with status ' + xhr.status));
                }
            });

            xhr.addEventListener('error', function () {
                reject(new Error('Network error during S3 upload.'));
            });

            xhr.addEventListener('abort', function () {
                reject(new Error('S3 upload was aborted.'));
            });

            xhr.open('PUT', presigned.url, true);
            xhr.setRequestHeader('Content-Type', nativeFile.type || 'application/octet-stream');
            xhr.send(nativeFile);
        });
    }

    function registerAttachment(key) {
        return fetch(config.registerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({ key: key })
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.error || 'Failed to register attachment.');
                }
                return data;
            });
        });
    }

    function completeUpload(up, file, attachment) {
        file.status = plupload.DONE;
        file.percent = 100;
        up.trigger('UploadProgress', file);

        if (typeof up.settings.multipart_params === 'undefined') {
            up.settings.multipart_params = {};
        }

        if (up.settings.wpUploaderSuccess) {
            up.settings.wpUploaderSuccess(file, attachment);
        } else {
            up.trigger('FileUploaded', file, {
                status: 201,
                response: JSON.stringify(attachment)
            });
        }

        startNextFile(up);
    }

    function triggerError(up, file, message) {
        file.status = plupload.FAILED;

        up.trigger('Error', {
            code: plupload.GENERIC_ERROR,
            message: message,
            file: file
        });

        startNextFile(up);
    }

    function startNextFile(up) {
        var pending = up.files.filter(function (f) {
            return f.status === plupload.QUEUED;
        });

        if (pending.length > 0) {
            up.start();
        } else {
            up.trigger('UploadComplete', up.files);
        }
    }
})();
