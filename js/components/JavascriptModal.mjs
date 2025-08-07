import {
  html,
  useEffect,
  useRef,
} from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

codeInput.registerTemplate(
  "syntax-highlighted",
  codeInput.templates.hljs(hljs)
);

const styles = {
  modal: css`
    position: fixed;
    z-index: 2001 /* nextcloud header has 2000 */;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;

    .container {
      background-color: var(--color-main-background);
      width: calc(100% - 2rem);
      max-width: 55rem;
      height: calc(100% - 2rem);
      max-height: 100rem;
      padding: 1rem;
      display: flex;
      gap: 1rem;
      flex-direction: column;
      border-radius: 0.5rem;
      box-shadow: 0px 10px 50px rgba(0, 0, 0, 0.4);
    }

    .content {
      display: flex;
      flex-direction: column;
      flex-grow: 1;
      overflow-y: auto;
    }

    h3 {
      margin: 0 0 0.5rem;
    }

    p {
      margin: 0 0 0.5rem;
    }

    code-input {
      border-radius: 0.5rem;
      min-height: 10rem;
      flex-grow: 1;
      margin: 0 !important;
      margin-bottom: 1.5rem !important;

      textarea {
        outline: none !important;
        box-shadow: none !important;
      }
    }

    table {
      overflow: hidden;
      border-radius: 2px;

      tr {
        background-color: initial !important;
      }

      td {
        width: auto;
        white-space: normal;
        padding: 0.5rem;
        vertical-align: top;
      }

      tr:not(:last-child) > td {
        border-bottom: 1px solid var(--color-background-dark);
      }

      td:first-child {
        padding-left: 0;
      }
      td:last-child {
        padding-right: 0;
      }

      td:first-child {
        white-space: nowrap;
      }
    }

    .actions {
      display: flex;
      justify-content: space-between;
    }
  `,
};

export default function JavascriptModal(props) {
  const codeInputRef = useRef(null);
  const { javascript: initialJavascript, onCancel, onSubmit } = props;

  useEffect(() => {
    document.body.style.overflow = "hidden";
    document.body.querySelector("#content").style.position = "static";

    return () => {
      document.body.style.overflow = "";
      document.body.querySelector("#content").style.position = "";
    };
  }, []);

  const handleClose = (event) => {
    event.preventDefault();

    const textarea = codeInputRef.current.querySelector("textarea");
    const hasUnsavedChanges =
      textarea.value !== initialJavascript.replaceAll("\r", "");
    if (hasUnsavedChanges && !confirm("Discard unsaved changes?")) {
      return;
    }

    onCancel();
  };

  const handleSubmit = (event) => {
    const textarea = codeInputRef.current.querySelector("textarea");

    event.preventDefault();
    onSubmit(textarea.value);
  };

  return html`
    <div
      className=${styles.modal}
      onClick=${(e) => e.target === e.currentTarget && handleClose(e)}
    >
      <div className="container">
        <div className="content">
          <h3>Edit custom JavaScript</h3>

          <p>
            The script will be executed on${" "}
            <code class="hljs-string">DOMContentLoaded</code>.
          </p>

          <code-input
            template="syntax-highlighted"
            language="JavaScript"
            placeholder=""
            ref=${codeInputRef}
          >
            ${initialJavascript}
          </code-input>

          <p>
            The following events are available for listening on${" "}
            <code class="hljs-variable">window</code>:
          </p>

          <table>
            <tbody>
              <tr>
                <td><code class="hljs-string">pf:image-loadstart</code></td>
                <td>Image starts loading.</td>
                <td>reader: file reader</td>
              </tr>
              <tr>
                <td><code class="hljs-string">pf:image-loadend</code></td>
                <td>Image was loaded and is ready to be shown</td>
                <td>
                  reader: file reader<br />
                  image
                </td>
              </tr>
              <tr>
                <td><code class="hljs-string">pf:image-fadestart</code></td>
                <td>
                  Image started fading in<br />
                  (not dispatched for first image)
                </td>
                <td>image</td>
              </tr>
              <tr>
                <td><code class="hljs-string">pf:image-visible</code></td>
                <td>Image is shown fully</td>
                <td>image</td>
              </tr>
              <tr>
                <td><code class="hljs-string">pf:frame-ready</code></td>
                <td>Frame is ready</td>
                <td>image: First image or null if empty frame</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div className="actions">
          <button onClick=${handleClose}>Cancel</button>
          <button className="primary" onClick=${handleSubmit}>Save</button>
        </div>
      </div>
    </div>
  `;
}
