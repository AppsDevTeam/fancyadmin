const run = (config) => {
    $('[data-adt-notifications]').on('click', function () {
        if (window.messaging) {
            Notification.requestPermission().then(function () {
                messaging.getToken().then(function (currentToken) {
                    $.nette.ajax({
                        url: config.setFirebaseTokenLink.replace('__token__', currentToken)
                    });
                }).catch(function (err) {
                    alert(_('appJs.firebase.error.notificationsPermissionError'));
                });

            }).catch(function (err) {
                alert(_('appJs.firebase.error.notificationsPermissionError'));
            });
        } else {
            alert(_('appJs.firebase.error.notificationsNotSupported'));
        }
    });
}

export default {run};