import { html, useState } from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

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
      padding: 1rem;
      display: flex;
      gap: 1rem;
      flex-direction: column;
      border-radius: 0.5rem;
      box-shadow: 0px 10px 50px rgba(0, 0, 0, 0.4);
    }

    .actions {
      display: flex;
      justify-content: space-between;
    }
  `,
};

export default function JavascriptModal(props) {
  const { javascript: initialJavascript, onCancel, onSubmit } = props;
  const [javascript, setJavascript] = useState(initialJavascript);

  const handleClose = (event) => {
    event.preventDefault();

    const hasUnsavedChanges = javascript !== initialJavascript;
    if (hasUnsavedChanges && !confirm("Discard unsaved changes?")) {
      return;
    }

    onCancel();
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit(javascript);
  };

  return html`
    <div
      className=${styles.modal}
      onClick=${(e) => e.target === e.currentTarget && closeModal()}
    >
      <div className="container">
        <div className="content">
          <textarea
            value=${javascript}
            onChange=${({ target }) => setJavascript(target.value)}
          ></textarea>
        </div>
        <div className="actions">
          <button onClick=${handleClose}>Cancel</button>
          <button className="primary" onClick=${handleSubmit}>Save</button>
        </div>
      </div>
    </div>
  `;
}
