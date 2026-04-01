import { initializeApp } from "firebase/app";
import { getMessaging, isSupported } from "firebase/messaging";

const run = async (config) => {
    const app = initializeApp(config.initializeConfig);

    const supported = await isSupported();
    const messaging = supported ? getMessaging(app) : null;

    navigator.serviceWorker.addEventListener('message', (event) => {
        const payload = event.data;
        $(document).trigger(`messaging.${payload.data.action}`, {
            action: payload.data.action,
            body: payload.data.body
        });
    });

    window.messaging = messaging;
}

export default {run};