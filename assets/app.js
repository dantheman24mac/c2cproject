(() => {
  const wordCount = (value) => {
    const trimmed = value.trim();
    if (!trimmed) {
      return 0;
    }
    return trimmed.split(/\s+/).length;
  };

  const attachWordCount = (fieldName) => {
    const textarea = document.querySelector(`textarea[name="${fieldName}"]`);
    const counter = document.querySelector(`[data-word-count-for="${fieldName}"]`);
    if (!textarea || !counter) {
      return;
    }

    const update = () => {
      const count = wordCount(textarea.value);
      counter.textContent = `${count} word${count === 1 ? "" : "s"}`;
    };

    textarea.addEventListener("input", update);
    update();
  };

  document.addEventListener("DOMContentLoaded", () => {
    attachWordCount("review_text");
    attachWordCount("description");
  });
})();
