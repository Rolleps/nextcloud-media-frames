import { html } from "../vendor/htm-preact-standalone.min.mjs";
import Breadcrumbs from "../components/Breadcrumbs.mjs";
import FrameFields from "../components/FrameFields.mjs";

export default function NewPage({ frame, requestToken, albums, urls }) {
  const breadcrumbItems = [
    { title: "Media Frames", url: urls.index },
    { title: "New frame" },
  ];

  return html`
    <>
      <form action=${urls.create} method="post">
        <${Breadcrumbs} items=${breadcrumbItems}>
          <button type="submit" class="primary">Save frame</button>
        <//>
        <${FrameFields}
          albums=${albums}
          frame=${frame}
          requestToken=${requestToken}
          foldersUrl=${urls.folders}
        />
      </form>
    </>
  `;
}
