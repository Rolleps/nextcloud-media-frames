import { html } from "../vendor/htm-preact-standalone.min.mjs";
import Breadcrumbs from "../components/Breadcrumbs.mjs";
import FrameFields from "../components/FrameFields.mjs";

export default function EditPage({ frame, requestToken, albums, urls }) {
  const breadcrumbItems = [
    { title: "Photo frames", url: urls.index },
    { title: frame.name },
  ];

  return html`
    <>
    <form action=${frame.urls.update} method="post">
      <${Breadcrumbs} items=${breadcrumbItems}>
        <button type="submit" class="primary">Save frame</button>
      <//>
      <${FrameFields}
        albums=${albums}
        frame=${frame}
        requestToken=${requestToken}
      >
      <//>
    <//>
  `;
}
