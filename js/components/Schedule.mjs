import { html } from "../vendor/htm-preact-standalone.min.mjs";
import { css } from "../vendor/emotion-css.min.mjs";

const styles = {
  time: css`
    font-family: monospace;
  `,
};

function formatDuration(seconds) {
  const s = parseInt(seconds);
  if (s >= 3600 && s % 3600 === 0) return `${s / 3600}h`;
  if (s >= 60 && s % 60 === 0) return `${s / 60} min`;
  return `${s}s`;
}

export default function Schedule({ imageDurationSeconds, startDayAt, endDayAt, className }) {
  const duration = formatDuration(imageDurationSeconds);
  const allDay = startDayAt === "00:00" && endDayAt === "00:00";

  return html`
    <p className=${className || ""}>
      <strong>Schedule:</strong><br />
      ${allDay
        ? html`All day: each photo shows for ${duration}`
        : html`
            ${startDayAt !== "00:00" && html`
              <span className=${styles.time}>00:00–${startDayAt}:</span>
              ${" "}"Pre-show" first photo<br />
            `}
            <span className=${styles.time}>${startDayAt}–${endDayAt}:</span>
            ${" "}each photo shows for ${duration}<br />
            ${endDayAt !== "00:00" && html`
              <span className=${styles.time}>${endDayAt}–00:00:</span>
              ${" "}keep showing last photo
            `}
          `
      }
    </p>
  `;
}
