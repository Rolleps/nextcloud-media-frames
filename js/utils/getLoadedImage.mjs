export default async function getLoadedImage(url) {
  const image = new Image();
  const loadPromise = new Promise((res, rej) => {
    image.onload = res;
    image.onerror = rej;
  });
  image.src = url;
  await loadPromise;

  return image;
}
