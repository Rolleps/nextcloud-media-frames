import {
  html,
  useEffect,
  useState,
} from "../vendor/htm-preact-standalone.min.mjs";
import { injectGlobal } from "../vendor/emotion-css.min.mjs";
import Frame from "../components/Frame.mjs";
import EmptyAlbum from "../components/EmptyAlbum.mjs";
import PhotoCouldNotBeLoaded from "../components/PhotoCouldNotBeLoaded.mjs";
import dispatch from "../utils/dispatch.mjs";

const rotationUnitRefreshInterval = {
  day: 1000 * 60, // One minute
  hour: 1000 * 60, // One minute
  minute: 1000, // One second
};

injectGlobal`
  :root {
    font-size: 16px;
  }
  body {
    margin: 0;
  }
`;

async function responseToBlobUrl(imageResponse) {
  // Read the image to a DataURL using the FileReader API
  // This is to prevent the browser from caching the image URL (which is the same for all images)
  const blob = await imageResponse.blob();

  const reader = new FileReader();
  const result = await new Promise((res, rej) => {
    dispatch("pf:image-loadstart", { reader });

    reader.onloadend = () => res(reader.result);
    reader.onerror = () => rej();
    reader.readAsDataURL(blob);
  });
  dispatch("pf:image-loadend", { reader });
  return result;
}

export default function FramePage(props) {
  const {
    showPhotoTimestamp,
    showClock,
    photoSize,
    backgroundType,
    backgroundColor,
  } = props;
  const imageUrl = location.href + "/image";
  const [images, setImages] = useState([]);
  const [albumIsEmpty, setAlbumIsEmpty] = useState(false);
  const [error, setError] = useState(false);

  const currentImage = images.at(-1);
  useEffect(() => {
    let timeout;

    const updateImage = async () => {
      const now = new Date();

      // If we already have an image, set a timeout up front,
      // and return early unless a new image is available
      if (currentImage) {
        timeout = setTimeout(updateImage, currentImage.refreshInterval);

        // Return early unless we've exceeded the image's expiry
        if (currentImage.expiresAt > now) return;

        // Fetch the newest image's expiry, continue if the expiry is
        // not the same as the current image (e.g. it really IS a new image)
        const headResponse = await fetch(imageUrl, {
          method: "HEAD",
          cache: "reload",
        });
        const nextExpiresAt = new Date(headResponse.headers.get("expires"));
        if (currentImage.expiresAt >= nextExpiresAt) return;
      }

      const imageResponse = await fetch(imageUrl);

      // If image returns a 404, it means the albums has no images
      if (imageResponse.status === 404) {
        if (timeout) clearTimeout(timeout);
        setAlbumIsEmpty(true);
        dispatch("pf:frame-ready", { image: null });
        return;
      }

      // Only continue if the response is ok
      if (!imageResponse.ok) {
        if (!currentImage) {
          setError(true);
          clearTimeout(timeout);
        }
        return;
      }

      const blobUrl = await responseToBlobUrl(imageResponse);

      // Prepare next image
      const rotationUnit = imageResponse.headers.get("X-Frame-Rotation-Unit");
      const nextImage = {
        url: blobUrl,
        expiresAt: new Date(imageResponse.headers.get("expires")),
        timestamp: new Date(
          imageResponse.headers.get("X-Photo-Timestamp") * 1000
        ),
        refreshInterval: rotationUnitRefreshInterval[rotationUnit],
      };

      setImages([currentImage, nextImage].filter(Boolean));

      // Remove previous image when the new image has faded in
      if (currentImage) {
        dispatch("pf:image-fadestart", { image: nextImage });
        setTimeout(() => {
          setImages([nextImage]);
          dispatch("pf:image-visible", { image: nextImage });
        }, 2000);
      } else {
        dispatch("pf:image-visible", { image: nextImage });
        dispatch("pf:frame-ready", { image: nextImage });
      }
    };

    timeout = setTimeout(updateImage, currentImage?.refreshInterval || 0);
    return () => clearTimeout(timeout);
  }, [currentImage]);

  if (albumIsEmpty) {
    return html` <${EmptyAlbum} /> `;
  }

  if (error) {
    return html` <${PhotoCouldNotBeLoaded} /> `;
  }

  return html`
    ${images.map(
      (image) =>
        html`<${Frame}
          key=${image.expiresAt}
          showPhotoTimestamp=${showPhotoTimestamp}
          showClock=${showClock}
          photoSize=${photoSize}
          backgroundType=${backgroundType}
          backgroundColor=${backgroundColor}
          image=${image}
        />`
    )}
  `;
}
