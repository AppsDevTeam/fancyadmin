// Initialize Firebase
import { initializeApp } from "firebase/app";
import { getMessaging, isSupported, onMessage } from "firebase/messaging";

const run = async (config) => {
    const app = initializeApp(config.initializeConfig);

    // Retrieve Firebase Messaging object.
    const supported = await isSupported();
    const messaging = supported ? getMessaging(app) : null;

    if (messaging) {
        onMessage(messaging, function (payload) {
            $(document).trigger(`messaging.${payload.data.action}`, {
                action: payload.data.action,
                body: payload.data.body
            });
        });
    }

    window.messaging = messaging;
}

export default {run};