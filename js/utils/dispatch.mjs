export default function dispatch(name, detail) {
  dispatchEvent(new CustomEvent(name, { detail }));
}
