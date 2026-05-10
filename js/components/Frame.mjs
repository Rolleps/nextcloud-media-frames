import {
  html,
  useCallback,
  useEffect,
  useRef,
} from "../vendor/htm-preact-standalone.min.mjs";
import { css, keyframes } from "../vendor/emotion-css.min.mjs";
import getLoadedImage from "../utils/getLoadedImage.mjs";
import renderSmartFittedImage from "../utils/renderSmartFittedImage.mjs";
import ImageDetails from "./ImageDetails.mjs";
import Clock from "./Clock.mjs";

const animations = {
  fadeIn: keyframes`
    from { opacity: 0; }
    to { opacity: 1; }
  `,
};

const styles = {
  frame: css`
    background-color: #000;
    position: absolute;
    width: 100%;
    height: 100%;
    font-family: "Noto Sans", sans-serif;

    & + & {
      animation: ${animations.fadeIn} 2s ease-in-out;
    }
  `,
  photoBackground: css`
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background-position: center;
    background-size: 100% 100%;
    filter: blur(6.25em) brightness(70%);
  `,
  colorBackground: css`
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
  `,
  photo: css`
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background-position: center;
    background-repeat: no-repeat;
    &.stretch { background-size: 100% 100%; }
    &.contain, &.smart-fit { background-size: contain; }
    &.cover { background-size: cover; }
  `,
  video: css`
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    object-fit: contain;
    background: #000;
  `,
  clockContainer: css`
    position: absolute; top: 0; left: 0; right: 0;
    display: flex; justify-content: end;
    padding: 1.2em;
  `,
  detailsContainer: css`
    position: absolute; bottom: 0; left: 0; right: 0;
    display: flex; justify-content: start;
    padding: 1em 1.2em;
  `,
};

export default function Frame(props) {
  const {
    showPhotoTimestamp,
    showPhotoPlace,
    showClock,
    photoSize,
    item,
    backgroundType,
    backgroundColor,
    onVideoEnded,
  } = props;

  const canvasRef = useRef();
  const loadedImageRef = useRef();
  const isVideo = item?.mediaType === "video";
  const showBackground = !isVideo && ["contain", "smart-fit"].includes(photoSize);
  const renderOnCanvas = !isVideo && photoSize === "smart-fit";

  const renderImage = useCallback(async () => {
    renderSmartFittedImage(loadedImageRef.current, canvasRef.current);
  }, []);

  useEffect(() => {
    if (!renderOnCanvas || !item?.url) return;

    (async () => {
      if (!loadedImageRef.current) {
        loadedImageRef.current = await getLoadedImage(item.url);
      }
      renderImage();
    })();

    window.addEventListener("resize", renderImage);
    return () => window.removeEventListener("resize", renderImage);
  }, [renderOnCanvas, item?.url]);

  if (!item) return null;

  return html`
    <div className=${styles.frame}>
      ${showBackground && html`
        ${backgroundType === "aura"
          ? html`<div className=${styles.photoBackground} style=${{ backgroundImage: `url("${item.url}")` }} />`
          : html`<div className=${styles.colorBackground} style=${{ backgroundColor }} />`
        }
      `}

      ${isVideo
        ? html`
            <video
              className=${styles.video}
              src=${item.url}
              autoplay
              muted
              playsinline
              onEnded=${onVideoEnded}
            />
          `
        : renderOnCanvas
          ? html`<canvas ref=${canvasRef} className=${styles.photo} />`
          : html`<div className=${[styles.photo, photoSize].join(" ")} style=${{ backgroundImage: `url("${item.url}")` }} />`
      }

      ${showClock && html`
        <div className=${styles.clockContainer}>
          <${Clock} image=${item} />
        </div>
      `}

      <div className=${styles.detailsContainer}>
        <${ImageDetails}
          showTimestamp=${showPhotoTimestamp}
          showPlace=${showPhotoPlace}
          image=${item}
        />
      </div>
    </div>
  `;
}
