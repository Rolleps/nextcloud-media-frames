import {
  html,
  useState,
  useEffect,
} from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

const styles = {
  modal: css`
    position: fixed;
    z-index: 2001;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
  `,
  container: css`
    background-color: var(--color-main-background);
    width: calc(100% - 2rem);
    max-width: 36rem;
    height: calc(100% - 4rem);
    max-height: 32rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-radius: 0.5rem;
    box-shadow: 0px 10px 50px rgba(0, 0, 0, 0.4);
  `,
  header: css`
    display: flex;
    align-items: center;
    justify-content: space-between;
    h3 { margin: 0; }
  `,
  closeBtn: css`
    background: none !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 0.3rem !important;
    font-size: 1.3rem;
    cursor: pointer;
    color: var(--color-text-lighter);
    line-height: 1;
    height: auto !important;
    min-height: unset !important;
    &:hover { color: var(--color-text-dark); }
  `,
  breadcrumb: css`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.1rem;
    background: var(--color-background-dark);
    padding: 0.4rem 0.7rem;
    border-radius: 4px;
    font-size: 0.9rem;
    min-height: 2rem;
  `,
  breadcrumbBtn: css`
    background: none !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 0.15rem !important;
    margin: 0 !important;
    cursor: pointer;
    color: var(--color-primary-element);
    font-size: 0.9rem;
    height: auto !important;
    min-height: unset !important;
    &:hover { text-decoration: underline; }
  `,
  sep: css`
    color: var(--color-text-lighter);
    user-select: none;
    padding: 0 0.05rem;
  `,
  folderList: css`
    flex: 1;
    overflow-y: auto;
    border: 1px solid var(--color-border);
    border-radius: 4px;
  `,
  folderRow: css`
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.75rem;
    cursor: pointer;
    border: none !important;
    box-shadow: none !important;
    background: none !important;
    width: 100%;
    text-align: left;
    font-size: 0.95rem;
    border-radius: 0 !important;
    height: auto !important;
    min-height: unset !important;
    &:hover { background: var(--color-background-hover) !important; }
    &:not(:last-child) { border-bottom: 1px solid var(--color-border) !important; }
  `,
  folderIcon: css`
    font-size: 1.1rem;
    flex-shrink: 0;
    line-height: 1;
  `,
  arrow: css`
    margin-left: auto;
    color: var(--color-text-lighter);
    flex-shrink: 0;
  `,
  empty: css`
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--color-text-lighter);
    font-size: 0.9rem;
    padding: 2rem;
    text-align: center;
  `,
  footer: css`
    display: flex;
    align-items: center;
    gap: 1rem;
  `,
  recursiveLabel: css`
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.9rem;
    cursor: pointer;
    flex: 1;
    user-select: none;
    * { cursor: pointer; }
  `,
  actions: css`
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
  `,
};

export default function FolderBrowser({ foldersUrl, onSelect, onCancel }) {
  const [currentPath, setCurrentPath] = useState("/");
  const [folders, setFolders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [recursive, setRecursive] = useState(true);

  useEffect(() => {
    document.body.style.overflow = "hidden";
    return () => { document.body.style.overflow = ""; };
  }, []);

  useEffect(() => {
    fetchFolders("/");
  }, []);

  const fetchFolders = async (path) => {
    setLoading(true);
    try {
      const res = await fetch(`${foldersUrl}?path=${encodeURIComponent(path)}`);
      const json = await res.json();
      setFolders(json.folders || []);
    } catch {
      setFolders([]);
    }
    setLoading(false);
  };

  const navigate = (path) => {
    setCurrentPath(path);
    fetchFolders(path);
  };

  // Build breadcrumb segments
  const segments = [{ label: "Home", path: "/" }];
  if (currentPath !== "/") {
    const parts = currentPath.replace(/^\//, "").split("/");
    parts.forEach((part, i) => {
      segments.push({ label: part, path: "/" + parts.slice(0, i + 1).join("/") });
    });
  }

  const handleSelect = (e) => {
    e.preventDefault();
    onSelect(currentPath, recursive);
  };

  return html`
    <div
      className=${styles.modal}
      onClick=${(e) => e.target === e.currentTarget && onCancel()}
    >
      <div className=${styles.container}>
        <div className=${styles.header}>
          <h3>Browse folders</h3>
          <button type="button" className=${styles.closeBtn} onClick=${onCancel} title="Close">×</button>
        </div>

        <div className=${styles.breadcrumb}>
          ${segments.map((seg, i) => html`
            ${i > 0 && html`<span className=${styles.sep}>/</span>`}
            <button
              type="button"
              className=${styles.breadcrumbBtn}
              onClick=${() => navigate(seg.path)}
            >${seg.label}</button>
          `)}
        </div>

        <div className=${styles.folderList}>
          ${loading
            ? html`<div className=${styles.empty}>Loading…</div>`
            : folders.length === 0
              ? html`<div className=${styles.empty}>No subfolders in this folder</div>`
              : folders.map((f) => html`
                  <button
                    type="button"
                    key=${f.path}
                    className=${styles.folderRow}
                    onClick=${() => navigate(f.path)}
                  >
                    <span className=${styles.folderIcon}>📁</span>
                    <span>${f.name}</span>
                    <span className=${styles.arrow}>›</span>
                  </button>
                `)
          }
        </div>

        <div className=${styles.footer}>
          <label className=${styles.recursiveLabel}>
            <input
              type="checkbox"
              checked=${recursive}
              onChange=${(e) => setRecursive(e.target.checked)}
            />
            <span>Include subfolders</span>
          </label>
          <div className=${styles.actions}>
            <button type="button" onClick=${onCancel}>Cancel</button>
            <button type="button" className="primary" onClick=${handleSelect}>
              Select
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}
