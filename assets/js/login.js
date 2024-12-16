const taglines = [
  "Furniture <span>Elegan</span> Untuk Rumah <span>Impian Anda</span>",
  "Desain <span>Berkualitas</span> Kenyamanan <span>Maksimal</span>",
  "Wujudkan Ruang <span>Impian</span> Dengan Sentuhan <span>Kami</span>",
];

let currentTaglineIndex = 0;
const taglineElement = document.getElementById("tagline-text");

function typeTagline(text) {
  return new Promise((resolve) => {
    taglineElement.innerHTML = "";
    const fragments = text.split(/(<span>.*?<\/span>|\s+)/);
    fragments.forEach((fragment, index) => {
      if (fragment.trim() !== "") {
        const span = document.createElement("span");
        span.className = "word";
        if (fragment.startsWith("<span>")) {
          span.classList.add("highlight");
          span.textContent = fragment.replace(/<\/?span>/g, "");
        } else {
          span.textContent = fragment;
        }
        span.style.transitionDelay = `${index * 50}ms`; // Reduced from 100ms to 50ms
        taglineElement.appendChild(span);
      }
    });
    void taglineElement.offsetWidth;

    Array.from(taglineElement.children).forEach((word) => {
      word.classList.add("visible");
    });

    setTimeout(resolve, fragments.length * 50 + 300);
  });
}

function eraseTagline() {
  return new Promise((resolve) => {
    const words = Array.from(taglineElement.children);
    words.forEach((word, index) => {
      word.style.transitionDelay = `${index * 50}ms`;
      word.classList.remove("visible");
    });

    setTimeout(() => {
      taglineElement.innerHTML = "";
      resolve();
    }, words.length * 50 + 300);
  });
}

async function cycleTaglines() {
  while (true) {
    await typeTagline(taglines[currentTaglineIndex]);
    await new Promise((resolve) => setTimeout(resolve, 5000)); // Increased from 3000ms to 5000ms
    await eraseTagline();
    currentTaglineIndex = (currentTaglineIndex + 1) % taglines.length;
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
}

cycleTaglines();
