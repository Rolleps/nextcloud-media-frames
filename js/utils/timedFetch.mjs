export default function timedFetch(ttl = 5000, url, options = {}) {
  const controller = new AbortController();
  const abortTimeout = setTimeout(() => controller.abort(), ttl);
  return fetch(url, { ...options, signal: controller.signal })
    .then((result) => {
      clearTimeout(abortTimeout);
      return result;
    })
    .catch((error) => {
      clearTimeout(abortTimeout);
      throw error;
    });
}
