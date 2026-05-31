import {
  html,
  useState,
  useEffect,
  useRef,
} from "../vendor/htm-preact-standalone.min.mjs";
import Frame from "./Frame.mjs";
import { css } from "../vendor/emotion-css.min.mjs";
import Screen from "./Screen.mjs";
import RadioButtons from "./RadioButtons.mjs";
import JavascriptModal from "./JavascriptModal.mjs";
import FolderBrowser from "./FolderBrowser.mjs";


const styles = {
  frameFields: css`
    display: flex;
    flex-direction: row;
    width: 100%;
    max-width: 1600px;
    gap: 3rem;
    @media screen and (max-width: 800px) {
      flex-direction: column;
      gap: 0;
    }
    > * { flex: 1 1 1%; }
    input, select { height: auto; }
  `,
  detailColumns: css`
    max-width: 30rem;
    display: flex;
    gap: 1rem;
    > * { flex: 1 1 1%; }
    input, select { width: 100%; }
    h3 { margin-top: 0.9rem; }
  `,
  radioButtons: css`
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
  `,
  fieldTitle: css`
    margin-top: 1.5rem;
    margin-bottom: 0.3rem;
    font-size: 1.2rem;
    font-weight: 600;
    & + * { margin-top: 0 !important; }
  `,
  tip: css`
    font-style: italic;
    font-size: 0.9em;
    margin-top: 0.5rem !important;
  `,
  preview: css`
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
  `,
  previewAndDisplayOptions: css`
    display: flex;
    flex-direction: column;
    @media screen and (max-width: 768px) { flex-flow: column-reverse; }
  `,
  displayOptionsColumns: css`
    display: flex;
    > * { flex: 1 1 0%; }
  `,
  error: css`color: var(--color-error);`,
  screen: css`
    padding: 1rem;
    max-width: 400px;
    @media screen and (min-width: 600px) { max-width: 600px; }
  `,
  backgroundInputs: css`
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    > * { margin: 0 !important; }
  `,
  colorInput: css`
    display: inline-block !important;
    align-self: stretch;
    width: 2.2rem !important;
    padding: 0.2rem !important;
  `,
  // Source list styles
  sourceList: css`
    list-style: none;
    padding: 0;
    margin: 0.5rem 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  `,
  sourceItem: css`
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--color-background-dark, #f0f0f0);
    border-radius: 6px;
    padding: 0.4rem 0.7rem;
    font-size: 0.9rem;
  `,
  sourceTag: css`
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-primary-text, #0082c9);
    min-width: 3.5rem;
  `,
  sourceName: css`flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;`,
  removeBtn: css`
    background: none !important;
    border: none;
    cursor: pointer;
    padding: 0 0.3rem;
    color: var(--color-error, #e74c3c);
    font-size: 1.1rem;
    line-height: 1;
    &:hover, &:focus { background: none !important; opacity: 0.8; }
  `,
  addSourceRow: css`
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-top: 0.5rem;
  `,
  passwordRow: css`
    display: flex;
    gap: 0.5rem;
    align-items: center;
  `,
  inlineLabel: css`
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
  `,
};

const testImageLandscape = {
  url: `${window.appPath}/img/landscape.jpg`,
  mediaType: "image",
  timestamp: new Date(),
  place: "A Beautiful Place",
};
const testImagePortrait = {
  url: `${window.appPath}/img/portrait.jpg`,
  mediaType: "image",
  timestamp: new Date(),
  place: "The Living Room",
};

export default function FrameFields(props) {
  const { frame, albums, requestToken, foldersUrl } = props;
  const sourcesInputRef = useRef();

  const [data, setData] = useState({
    name: frame.name || "",
    sources: Array.isArray(frame.sources) ? frame.sources : [],
    selectionMethod: frame.selectionMethod || "latest",
    favorNewAdditions: frame.favorNewAdditions ?? false,
    showPhotoTimestamp: frame.showPhotoTimestamp ?? true,
    showPhotoPlace: frame.showPhotoPlace ?? false,
    showClock: frame.showClock ?? false,
    photoSize: frame.photoSize || "smart-fit",
    backgroundType: frame.backgroundType || "aura",
    backgroundColor: frame.backgroundColor || "#000000",
    rotationUnit: frame.rotationUnit || "hour",
    rotationsPerUnit: frame.rotationsPerUnit || 1,
    imageDurationSeconds: frame.imageDurationSeconds || 30,
    videoDuration: frame.videoDuration || "full",
    videoFixedDurationSeconds: frame.videoFixedDurationSeconds || 30,
    devicePassword: "",
    clearDevicePassword: false,
    javascript: frame.javascript || "",
  });

  const [newSourceType, setNewSourceType] = useState("album");
  const [newAlbumId, setNewAlbumId] = useState("");
  const [openedModal, setOpenedModal] = useState(null);

  const handleInput = ({ target: { name, value, checked, type } }) => {
    setData((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value,
    }));
  };

  const handleJavascriptSubmitted = (javascript) => {
    setData((prev) => ({ ...prev, javascript }));
    setOpenedModal(null);
  };

  useEffect(() => {
    if (sourcesInputRef.current) {
      sourcesInputRef.current.value = JSON.stringify(data.sources);
    }
  }, [data.sources]);

  const addAlbumSource = () => {
    if (!newAlbumId) return;
    const album = albums.find((a) => String(a.id) === String(newAlbumId));
    if (!album) return;
    setData((prev) => ({
      ...prev,
      sources: [...prev.sources, { type: "album", albumId: parseInt(newAlbumId), title: album.title }],
    }));
    setNewAlbumId("");
  };

  const addFolderSource = (path, recursive) => {
    setData((prev) => ({
      ...prev,
      sources: [...prev.sources, { type: "folder", path, recursive }],
    }));
    setOpenedModal(null);
  };

  const removeSource = (index) => {
    setData((prev) => ({
      ...prev,
      sources: prev.sources.filter((_, i) => i !== index),
    }));
  };

  const sourcesJson = JSON.stringify(data.sources);

  return html`
    <div className=${styles.frameFields}>
      <div>
        <input type="hidden" name="requesttoken" value="${requestToken}" />
        <input type="hidden" name="sources" ref=${sourcesInputRef} />

        <div>
          <h3 className=${styles.fieldTitle}>Name</h3>
          <input
            name="name"
            placeholder="Give this frame a name"
            required
            value=${data.name}
            onInput=${handleInput}
            style="max-width:20rem;"
          />
        </div>

        <div>
          <h3 className=${styles.fieldTitle}>Media sources</h3>
          <p className=${styles.tip}>
            Add one or more albums or folders. Media from all sources is pooled together.
          </p>

          ${data.sources.length > 0 && html`
            <ul className=${styles.sourceList}>
              ${data.sources.map((src, i) => html`
                <li key=${i} className=${styles.sourceItem}>
                  <span className=${styles.sourceTag}>${src.type}</span>
                  <span className=${styles.sourceName}>
                    ${src.type === "album"
                      ? (src.title || "Album #" + src.albumId)
                      : src.path + (src.recursive ? " (recursive)" : "")
                    }
                  </span>
                  <button
                    type="button"
                    className=${styles.removeBtn}
                    onClick=${() => removeSource(i)}
                    title="Remove source"
                  >×</button>
                </li>
              `)}
            </ul>
          `}

          <div className=${styles.addSourceRow}>
            <select
              value=${newSourceType}
              onChange=${(e) => setNewSourceType(e.target.value)}
            >
              <option value="album">Album</option>
              <option value="folder">Folder</option>
            </select>

            ${newSourceType === "album"
              ? html`
                  <select
                    value=${newAlbumId}
                    onChange=${(e) => setNewAlbumId(e.target.value)}
                    style="flex:1;"
                  >
                    <option value="" disabled>Choose album…</option>
                    ${albums.map((a) => html`
                      <option key=${a.id} value=${a.id}>${a.title}</option>
                    `)}
                  </select>
                  <button type="button" onClick=${addAlbumSource}>Add</button>
                `
              : html`
                  <button
                    type="button"
                    onClick=${() => setOpenedModal("folderBrowser")}
                    style="flex:1;"
                  >Browse folders…</button>
                `
            }
          </div>
        </div>

        <div>
          <h3 className=${styles.fieldTitle}>Selection method</h3>
          <div className=${styles.radioButtons}>
            <${RadioButtons}
              name="selectionMethod"
              required
              onChange=${handleInput}
              value=${data.selectionMethod}
              options=${[
                { value: "latest", label: html`Pick the <strong>latest</strong> item` },
                { value: "oldest", label: html`Pick the <strong>oldest</strong> item` },
                { value: "random", label: html`Pick a <strong>random</strong> item` },
              ]}
            />
          </div>

          <p>
            <label>
              <input
                type="checkbox"
                name="favorNewAdditions"
                checked=${data.favorNewAdditions}
                onChange=${handleInput}
              />
              <span>Prioritize items added within the last 7 days</span>
            </label>
          </p>
        </div>

        <div>
          <input type="hidden" name="rotationUnit" value=${data.rotationUnit} />
          <input type="hidden" name="rotationsPerUnit" value=${data.rotationsPerUnit} />
          <h3 className=${styles.fieldTitle}>Display duration</h3>
          <p>
            Photos & documents:${" "}
            <input
              type="number"
              name="imageDurationSeconds"
              value=${data.imageDurationSeconds}
              min="1"
              max="86400"
              style="width:5rem;"
              onInput=${handleInput}
            />
            ${" "}seconds each
            <br />
          </p>
          <input type="hidden" name="startDayAt" value="00:00" />
          <input type="hidden" name="endDayAt" value="00:00" />
          <p style="margin-top:0.5rem;">
            Videos:${" "}
            <select name="videoDuration" value=${data.videoDuration} onChange=${handleInput}>
              <option value="full">Play to end, then advance</option>
              <option value="fixed">Fixed duration</option>
            </select>
            ${data.videoDuration === "fixed" && html`
              ${" "}
              <input
                type="number"
                name="videoFixedDurationSeconds"
                value=${data.videoFixedDurationSeconds}
                min="1"
                max="3600"
                style="width:5rem;"
                onInput=${handleInput}
              />
              ${" "}seconds
            `}
          </p>
        </div>

        <div>
          <h3 className=${styles.fieldTitle}>Device access password <span style="font-size:0.8rem;font-weight:400;color:var(--color-text-lighter)">(optional)</span></h3>
          <p className=${styles.tip}>
            If set, only devices that have entered this password can view the frame URL.
            Share the URL as <code>…/{shareToken}?key=PASSWORD</code> to auto-unlock on new devices.
          </p>

          ${frame.hasDevicePassword && html`
            <p>
              <label className=${styles.inlineLabel}>
                <input
                  type="checkbox"
                  name="clearDevicePassword"
                  checked=${data.clearDevicePassword}
                  onChange=${handleInput}
                />
                <span>Remove existing password</span>
              </label>
            </p>
          `}

          ${!data.clearDevicePassword && html`
            <div className=${styles.passwordRow}>
              <input
                type="password"
                name="devicePassword"
                placeholder=${frame.hasDevicePassword ? "Leave blank to keep current password" : "Set a password…"}
                value=${data.devicePassword}
                onInput=${handleInput}
                style="max-width:20rem;"
                autocomplete="new-password"
              />
            </div>
          `}
        </div>
      </div>

      <div>
        <div className=${styles.previewAndDisplayOptions}>
          <div>
            <div className=${styles.preview}>
              <${Screen} className=${styles.screen}>
                <${Frame}
                  showPhotoTimestamp=${data.showPhotoTimestamp}
                  showPhotoPlace=${data.showPhotoPlace}
                  showClock=${data.showClock}
                  photoSize=${data.photoSize}
                  backgroundType=${data.backgroundType}
                  backgroundColor=${data.backgroundColor}
                  item=${testImageLandscape}
                />
              <//>
              <${Screen} className=${styles.screen}>
                <${Frame}
                  showPhotoTimestamp=${data.showPhotoTimestamp}
                  showPhotoPlace=${data.showPhotoPlace}
                  showClock=${data.showClock}
                  photoSize=${data.photoSize}
                  backgroundType=${data.backgroundType}
                  backgroundColor=${data.backgroundColor}
                  item=${testImagePortrait}
                />
              <//>
            </div>
            <p className="${styles.tip} text-center">
              Preview at 1280×800 (typical tablet)
            </p>
          </div>

          <div>
            <h3 className=${styles.fieldTitle}>Display options</h3>
            <div className=${styles.displayOptionsColumns}>
              <div>
                <label><input type="checkbox" name="showPhotoTimestamp" checked=${data.showPhotoTimestamp} onChange=${handleInput} /> <span>Show date</span></label>
              </div>
              <div>
                <label><input type="checkbox" name="showPhotoPlace" checked=${data.showPhotoPlace} onChange=${handleInput} /> <span>Show place</span></label>
              </div>
              <div>
                <label><input type="checkbox" name="showClock" checked=${data.showClock} onChange=${handleInput} /> <span>Show clock</span></label>
              </div>
            </div>

            <div className=${styles.radioButtons}>
              <${RadioButtons}
                name="photoSize"
                required
                value=${data.photoSize}
                onChange=${handleInput}
                options=${[
                  { value: "smart-fit", label: html`<strong>Smart fit</strong>: fit frame, keep ≥75% visible` },
                  { value: "contain", label: html`<strong>Contain</strong>: show full image` },
                  { value: "cover", label: html`<strong>Cover</strong>: fill frame, crop edges` },
                  { value: "stretch", label: html`<strong>Stretch</strong>: fill frame, distort` },
                ]}
              />
            </div>

            ${["smart-fit", "contain"].includes(data.photoSize)
              ? html`
                  <p>
                    Fill uncovered areas with${" "}
                    <span className=${styles.backgroundInputs}>
                      <select name="backgroundType" value=${data.backgroundType} onChange=${handleInput}>
                        <option value="aura">aura</option>
                        <option value="color">a background color</option>
                      </select>
                      ${data.backgroundType === "color"
                        ? html`${": "}<input name="backgroundColor" value=${data.backgroundColor} className=${styles.colorInput} onChange=${handleInput} type="color" />`
                        : html`<input type="hidden" name="backgroundColor" value=${data.backgroundColor} />`
                      }
                    </span>
                  </p>
                `
              : html`
                  <input type="hidden" name="backgroundType" value=${data.backgroundType} />
                  <input type="hidden" name="backgroundColor" value=${data.backgroundColor} />
                `
            }
          </div>
        </div>

        <div>
          <h3 className=${styles.fieldTitle}>Advanced options</h3>
          <input type="hidden" name="javascript" value=${data.javascript} />
          <button onClick=${(e) => { setOpenedModal("javascript"); e.preventDefault(); }}>
            Custom JavaScript
          </button>
        </div>
      </div>

      ${openedModal === "javascript" && html`
        <${JavascriptModal}
          javascript=${data.javascript}
          onCancel=${() => setOpenedModal(null)}
          onSubmit=${handleJavascriptSubmitted}
        />
      `}

      ${openedModal === "folderBrowser" && html`
        <${FolderBrowser}
          foldersUrl=${foldersUrl}
          onSelect=${addFolderSource}
          onCancel=${() => setOpenedModal(null)}
        />
      `}
    </div>
  `;
}
