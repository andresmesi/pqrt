/**
 * toggle-mode.js
 *
 * This script toggles between light and dark modes.
 * It stores the user's preference in localStorage and updates the mode toggle icons.
 */

// Global function to toggle the mode.
window.toggleMode = function() {
  console.log("toggleMode() executed");
  
  // Toggle the "night" class on the body element.
  const isNight = document.body.classList.toggle("night");

  // Save the preference in localStorage.
  localStorage.setItem("mode", isNight ? "night" : "day");

  // Update the display of sun and moon icons.
  const sunIcon = document.querySelector(".mode-toggle .fa-sun");
  const moonIcon = document.querySelector(".mode-toggle .fa-moon");
  if (sunIcon && moonIcon) {
    sunIcon.style.display = isNight ? "none" : "inline";
    moonIcon.style.display = isNight ? "inline" : "none";
  }

  console.log("Toggled to:", isNight ? "night" : "day");
};

// Restore saved mode on DOMContentLoaded.
document.addEventListener("DOMContentLoaded", () => {
  const savedMode = localStorage.getItem("mode");

  if (savedMode === "night") {
    document.body.classList.add("night");

    const sunIcon = document.querySelector(".mode-toggle .fa-sun");
    const moonIcon = document.querySelector(".mode-toggle .fa-moon");
    if (sunIcon && moonIcon) {
      sunIcon.style.display = "none";
      moonIcon.style.display = "inline";
    }
    console.log("Restored mode: night");
  } else {
    console.log("Restored mode: day (or not set)");
  }
});