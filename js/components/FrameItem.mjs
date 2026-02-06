import {
  html,
  useEffect,
  useRef,
} from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

import CopyButton from "../components/CopyButton.mjs";
import Schedule from "./Schedule.mjs";
import Screen from "./Screen.mjs";

const fullUrl = (path) => location.origin + path;

const styles = {
  frame: css`
    display: flex;
    flex-direction: column;

    p {
      font-size: 16px;
      font-weight: 500;
      margin: 0;
    }

    h2 {
      font-size: 1.4rem;
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.06rem;
      margin-top: 0.7rem;
      margin-bottom: 1rem;
    }
  `,
  info: css`
    padding: 0 0.9rem;
    display: flex;
    flex-direction: column;
  `,
  actions: css`
    display: flex;
    gap: 0.2rem 0.2rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
  `,
  iframeContainer: css`
    --iframe-scale: 0.1;
    width: 100%;
    aspect-ratio: 16/10;
    overflow: hidden;
  `,
  iframe: css`
    width: 1280px;
    height: 800px;
    transform: scale(var(--iframe-scale));
    transform-origin: 0% 0%;
  `,
};

export default function FrameItem(props) {
  const { frame, onShowQRCode, onDelete } = props;
  const iframeContainerRef = useRef();

  useEffect(() => {
    const updateIframeScale = () => {
      const elm = iframeContainerRef.current;
      elm.style.setProperty("--iframe-scale", elm.offsetWidth / 1280);
    };
    window.addEventListener("resize", updateIframeScale);
    updateIframeScale();

    return () => window.removeEventListener("resize", updateIframeScale);
  }, []);

  return html`
    <div className=${styles.frame}>
      <${Screen} className=${styles.preview}>
        <div ref=${iframeContainerRef} className=${styles.iframeContainer}>
          <iframe className=${styles.iframe} src=${frame.urls.show} />
        </div>
      <//>
      <div className=${styles.info}>
        <h2>${frame.name}</h2>

        <div className=${styles.actions}>
          <a target="_BLANK" href=${frame.urls.show}>
            <button className="primary">Show</button>
          </a>
          <a href=${frame.urls.edit}>
            <button>Edit</button>
          </a>
          <button onClick=${() => onShowQRCode(fullUrl(frame.urls.show))}>
            QR
          </button>
          <${CopyButton} data=${fullUrl(frame.urls.show)} copiedText="Copied">
            Copy link
          <//>

          <div className="grow" />

          <button className="error" onClick=${() => onDelete(frame)}>
            Delete
          </button>
        </div>

        <p><strong>Album:</strong> ${frame.albumName}</p>

        <p>
          <strong>Select:</strong>
          ${" "}
          ${{
            latest: "Latest",
            oldest: "Oldest",
            random: "Random",
          }[frame.selectionMethod]}
        </p>
        <${Schedule} ...${frame} />
      </div>
    </div>
  `;
}
