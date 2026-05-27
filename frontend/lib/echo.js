import Echo from "laravel-echo";
import Pusher from "pusher-js";

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
const port = import.meta.env.VITE_REVERB_PORT ?? "8080";
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? "http";

window.Pusher = Pusher;

window.Echo = key
  ? new Echo({
      broadcaster: "reverb",
      key,
      wsHost: host,
      wsPort: Number(port),
      wssPort: Number(port),
      forceTLS: scheme === "https",
      enabledTransports: ["ws", "wss"],
    })
  : null;

export default window.Echo;
