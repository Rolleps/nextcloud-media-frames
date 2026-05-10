import {
  html,
  useEffect,
  useRef,
  useState,
} from "../vendor/htm-preact-standalone.min.mjs";
import { injectGlobal } from "../vendor/emotion-css.min.mjs";
import Frame from "../components/Frame.mjs";
import EmptyAlbum from "../components/EmptyAlbum.mjs";
import PhotoCouldNotBeLoaded from "../components/PhotoCouldNotBeLoaded.mjs";
import dispatch from "../utils/dispatch.mjs";
import timedFetch from "../utils/timedFetch.mjs";

injectGlobal`
  :root { font-size: 16px; }
  body { margin: 0; }
`;

class SourceEmptyError extends Error {}
class UpdateMediaError extends Error {}

/**
 * Convert a fetch response to a blob URL.
 * Only used for images/documents — never for videos (too heavy on low-RAM devices).
 */
async function responseToBlobUrl(response) {
  const blob = await response.blob();
  const reader = new FileReader();
  dispatch("mf:media-loadstart", { reader });
  const result = await new Promise((res, rej) => {
    reader.onloadend = () => res(reader.result);
    reader.onerror = () => rej();
    reader.readAsDataURL(blob);
  });
  dispatch("mf:media-loadend", { reader });
  return result;
}

let nextTimeoutAt = Date.now();
const getNextTimeoutDelay = (current) => {
  if (!current) return 0;
  while (nextTimeoutAt <= Date.now()) nextTimeoutAt += 5000;
  return nextTimeoutAt - Date.now();
};

export default function FramePage(props) {
  const {
    showPhotoTimestamp,
    showPhotoPlace,
    showClock,
    photoSize,
    backgroundType,
    backgroundColor,
    mediaUrl,
    shareToken,
  } = props;

  const [items, setItems] = useState([]);
  const [sourceEmpty, setSourceEmpty] = useState(false);
  const [error, setError] = useState(false);
  const currentRef = useRef(null);
  const videoAdvanceRef = useRef(null); // called when video ends

  useEffect(() => {
    let loopTimeout;

    const fetchNextMedia = async () => {
      // HEAD check: skip fetch if server says item hasn't changed yet
      if (currentRef.current && currentRef.current.mediaType !== "video") {
        const headRes = await timedFetch(10000, mediaUrl, { method: "HEAD", cache: "reload" });
        const nextExpires = new Date(headRes.headers.get("expires"));
        if (currentRef.current.expiresAt >= nextExpires) return;
      }

      const res = await timedFetch(15000, mediaUrl, { cache: "reload" });

      if (res.status === 404) throw new SourceEmptyError();
      if (!res.ok) throw new UpdateMediaError();

      const mediaType = res.headers.get("X-Media-Type") || "image";
      const fileId = res.headers.get("X-File-Id");
      const expiresAt = new Date(res.headers.get("expires"));
      const timestamp = new Date(Number(res.headers.get("X-Photo-Timestamp")) * 1000);
      const place = decodeURIComponent(res.headers.get("X-Photo-Place") || "");
      const duration = res.headers.get("X-Duration") || "full";

      let url;
      if (mediaType === "video") {
        // For video: use dedicated streaming endpoint — no blob conversion (saves RAM on Pi)
        const base = mediaUrl.replace(/\/media$/, "");
        url = `${base}/file/${fileId}`;
      } else {
        // For image/document: blob URL prevents browser from caching the rotating preview
        url = await responseToBlobUrl(res);
      }

      const next = { url, mediaType, expiresAt, timestamp, place, duration, fileId };
      currentRef.current = next;

      let hadPrevious = false;
      setItems((prev) => {
        hadPrevious = prev.length > 0;
        return [prev.at(-1), next].filter(Boolean);
      });

      // After fade-in delay, clear the previous item
      if (hadPrevious) {
        dispatch("mf:media-fadestart", { item: next });
        await new Promise((resolve) => setTimeout(() => {
          setItems([next]);
          dispatch("mf:media-visible", { item: next });
          resolve();
        }, 2000));
      } else {
        dispatch("mf:media-visible", { item: next });
        dispatch("mf:frame-ready", { item: next });
      }
    };

    const loop = async () => {
      try {
        const current = currentRef.current;
        if (!current) return;

        const isExpired = current.mediaType === "video"
          ? false // videos advance via videoAdvanceRef callback, not time
          : Date.now() > current.expiresAt;

        if (isExpired) await fetchNextMedia();
      } finally {
        loopTimeout = setTimeout(loop, getNextTimeoutDelay(currentRef.current));
      }
    };

    // Wire up video-ended callback
    videoAdvanceRef.current = async () => {
      currentRef.current = null; // force fetch
      try {
        await fetchNextMedia();
        loopTimeout = setTimeout(loop, getNextTimeoutDelay(currentRef.current));
      } catch {
        setError(true);
      }
    };

    fetchNextMedia()
      .then(() => {
        loopTimeout = setTimeout(loop, getNextTimeoutDelay(currentRef.current));
      })
      .catch((e) => {
        if (e instanceof SourceEmptyError) setSourceEmpty(true);
        setError(true);
      });

    return () => {
      clearTimeout(loopTimeout);
      videoAdvanceRef.current = null;
    };
  }, []);

  if (sourceEmpty) return html`<${EmptyAlbum} />`;
  if (error && items.length === 0) return html`<${PhotoCouldNotBeLoaded} />`;

  return html`
    ${items.map((item) =>
      html`<${Frame}
        key=${item.fileId + "-" + item.mediaType}
        showPhotoTimestamp=${showPhotoTimestamp}
        showPhotoPlace=${showPhotoPlace}
        showClock=${showClock}
        photoSize=${photoSize}
        backgroundType=${backgroundType}
        backgroundColor=${backgroundColor}
        item=${item}
        onVideoEnded=${() => videoAdvanceRef.current?.()}
      />`
    )}
  `;
}
