import { html, useRef } from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

codeInput.registerTemplate(
  "syntax-highlighted",
  codeInput.templates.hljs(hljs)
);

const styles = {
  modal: css`
    position: fixed;
    z-index: 1;
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
      max-width: 55rem;
      width: calc(100% - 2rem);
      padding: 1rem;
      display: flex;
      gap: 1rem;
      flex-direction: column;
      border-radius: 0.5rem;
      box-shadow: 0px 10px 50px rgba(0, 0, 0, 0.4);
    }

    code-input {
      margin: 0 !important;

      textarea {
        outline: none !important;
        box-shadow: none !important;
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
            Script will execute on <i>DOMContentLoaded</i>. Available events
            are:
          </p>

          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Event details</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>pf:image-loadstart</td>
                <td>Image starts loading.</td>
                <td>reader: file reader</td>
              </tr>
              <tr>
                <td>pf:image-loadend</td>
                <td>Image was loaded and is ready to be shown</td>
                <td>
                  reader: file reader<br />
                  image
                </td>
              </tr>
              <tr>
                <td>pf:image-fadestart</td>
                <td>
                  Image started fading in (not dispatched for first image)
                </td>
                <td>image</td>
              </tr>
              <tr>
                <td>pf:image-visible</td>
                <td>Image is shown fully</td>
                <td>image</td>
              </tr>
              <tr>
                <td>pf:frame-ready</td>
                <td>Frame is ready</td>
                <td>image: First image or null if empty frame</td>
              </tr>
            </tbody>
          </table>

          <code-input
            template="syntax-highlighted"
            language="JavaScript"
            ref=${codeInputRef}
          >
            ${initialJavascript}
          </code-input>
        </div>
        <div className="actions">
          <button onClick=${handleClose}>Cancel</button>
          <button className="primary" onClick=${handleSubmit}>Save</button>
        </div>
      </div>
    </div>
  `;
}
