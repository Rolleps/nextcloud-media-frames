import { html, useRef } from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

codeInput.registerTemplate(
  "syntax-highlighted",
  codeInput.templates.hljs(hljs, [
    new codeInput.plugins.Indent(true, 2), // Allow Tab-key indentation, with 2 spaces indentation
  ])
);

const styles = {
  modal: css`
    position: fixed;
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
      width: 50rem;
      max-width: 100%;
      padding: 1rem;
      display: flex;
      gap: 1rem;
      flex-direction: column;
      border-radius: 0.5rem;
      box-shadow: 0px 10px 50px rgba(0, 0, 0, 0.4);
    }

    code-input textarea {
      outline: none !important;
      box-shadow: none !important;
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
