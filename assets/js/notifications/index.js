import { getToken } from "firebase/messaging";

const run = (config) => {
    $('[data-adt-notifications]').on('click', function () {
        if (window.messaging) {
            Notification.requestPermission().then(function (permission) {
                if (permission !== 'granted') {
                    alert(_('appJs.firebase.error.notificationsPermissionError'));
                    return;
                }

                getToken(window.messaging, { vapidKey: config.vapidKey })
                    .then(function (currentToken) {
                        if (currentToken) {
                            $.nette.ajax({
                                url: config.setFirebaseTokenLink.replace('__firebaseToken__', currentToken)
                            });
                        } else {
                            alert(_('appJs.firebase.error.notificationsPermissionError'));
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        alert(_('appJs.firebase.error.notificationsPermissionError'));
                    });
            });
        } else {
            alert(_('appJs.firebase.error.notificationsNotSupported'));
        }
    });
}

export default { run };