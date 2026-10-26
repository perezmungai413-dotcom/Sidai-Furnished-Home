const menuToggle = document.querySelector(".menu-toggle");
const siteNav = document.querySelector(".site-nav");

if (menuToggle && siteNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Open navigation" : "Close navigation");
    siteNav.classList.toggle("is-open", !isOpen);
  });

  siteNav.addEventListener("click", (event) => {
    if (event.target instanceof HTMLAnchorElement && event.target.hash) {
      menuToggle.setAttribute("aria-expanded", "false");
      menuToggle.setAttribute("aria-label", "Open navigation");
      siteNav.classList.remove("is-open");
    }
  });
}

const localDate = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const today = localDate(new Date());
const checkInFields = document.querySelectorAll('input[name="check_in"]');
const checkOutFields = document.querySelectorAll('input[name="check_out"]');

checkInFields.forEach((field) => {
  field.min = today;
  field.addEventListener("change", () => {
    const checkout = field.form?.elements.namedItem("check_out");
    if (checkout instanceof HTMLInputElement) {
      const minimumDate = field.value ? new Date(`${field.value}T00:00:00`) : new Date();
      if (field.value) minimumDate.setDate(minimumDate.getDate() + 1);
      const nextDay = localDate(minimumDate);
      checkout.min = nextDay;
      if (checkout.value && checkout.value < nextDay) checkout.value = "";
    }
  });
});

checkOutFields.forEach((field) => {
  field.min = today;
});

const year = document.getElementById("year");
if (year) year.textContent = String(new Date().getFullYear());

const lightbox = document.querySelector(".lightbox");
const lightboxImage = lightbox?.querySelector("img");
const lightboxClose = lightbox?.querySelector(".lightbox-close");

if (lightbox instanceof HTMLDialogElement && lightboxImage instanceof HTMLImageElement) {
  document.querySelectorAll("[data-lightbox]").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      lightboxImage.src = link.getAttribute("href") || "";
      lightboxImage.alt = link.querySelector("img")?.alt || "";
      lightbox.showModal();
    });
  });

  lightboxClose?.addEventListener("click", () => lightbox.close());
  lightbox.addEventListener("click", (event) => {
    if (event.target === lightbox) lightbox.close();
  });
  lightbox.addEventListener("close", () => {
    lightboxImage.removeAttribute("src");
    lightboxImage.alt = "";
  });
}

const reservationForm = document.getElementById("reservationForm");

if (reservationForm instanceof HTMLFormElement) {
  const params = new URLSearchParams(window.location.search);
  const interestToRequest = {
    "guest-services": "general",
    directions: "general",
  };
  const requestType = params.get("request_type") || interestToRequest[params.get("interest") || ""] || params.get("interest");
  if (requestType) params.set("request_type", requestType);
  ["check_in", "check_out", "number_of_guests", "property_type", "request_type", "special_requests"].forEach((key) => {
    const value = params.get(key);
    const field = reservationForm.elements.namedItem(key);
    if (value && field instanceof HTMLSelectElement) {
      if ([...field.options].some((option) => option.value === value)) field.value = value;
    } else if (value && field instanceof HTMLInputElement) {
      field.value = value;
    }
  });

  const requestTypeField = reservationForm.elements.namedItem("request_type");
  const stayFields = reservationForm.querySelectorAll(".stay-field");
  const setStayFields = () => {
    const isStay = requestTypeField instanceof HTMLSelectElement && requestTypeField.value === "stay";
    stayFields.forEach((container) => {
      container.toggleAttribute("hidden", !isStay);
      container.querySelectorAll("input, select").forEach((field) => {
        if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
          field.required = isStay;
        }
      });
    });
  };
  if (requestTypeField instanceof HTMLSelectElement) {
    requestTypeField.addEventListener("change", setStayFields);
    setStayFields();
  }

  const checkIn = reservationForm.elements.namedItem("check_in");
  const checkOut = reservationForm.elements.namedItem("check_out");
  if (checkIn instanceof HTMLInputElement && checkOut instanceof HTMLInputElement && checkIn.value) {
    const nextDate = new Date(`${checkIn.value}T00:00:00`);
    nextDate.setDate(nextDate.getDate() + 1);
    checkOut.min = localDate(nextDate);
  }

  const status = document.getElementById("form-status");
  const submitButton = reservationForm.querySelector('button[type="submit"]');

  if (location.hostname.endsWith(".github.io") && status instanceof HTMLElement && submitButton instanceof HTMLButtonElement) {
    status.dataset.state = "unavailable";
    status.textContent = "Online enquiries are not available on this static site yet. The form will be enabled once PHP hosting is configured.";
    submitButton.disabled = true;
  }

  reservationForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (submitButton instanceof HTMLButtonElement && submitButton.disabled) return;
    if (!reservationForm.reportValidity()) return;

    const isStay = requestTypeField instanceof HTMLSelectElement && requestTypeField.value === "stay";
    if (isStay && checkIn instanceof HTMLInputElement && checkOut instanceof HTMLInputElement && checkOut.value <= checkIn.value) {
      checkOut.setCustomValidity("Check-out must be after check-in.");
      checkOut.reportValidity();
      checkOut.setCustomValidity("");
      return;
    }

    if (!(status instanceof HTMLElement) || !(submitButton instanceof HTMLButtonElement)) return;
    status.removeAttribute("data-state");
    status.textContent = "";
    submitButton.disabled = true;
    submitButton.textContent = "Sending enquiry…";

    try {
      const response = await fetch(reservationForm.action, {
        method: "POST",
        body: new FormData(reservationForm),
        headers: { Accept: "application/json" },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "We couldn't send your enquiry. Please check your details and try again.");
      }
      status.dataset.state = "success";
      status.textContent = `${result.message} This is an enquiry only; your stay is not confirmed yet.`;
      reservationForm.reset();
      setStayFields();
      if (checkIn instanceof HTMLInputElement) checkIn.min = today;
      if (checkOut instanceof HTMLInputElement) checkOut.min = today;
    } catch (error) {
      status.dataset.state = "error";
      status.textContent = error instanceof Error
        ? error.message
        : "We couldn't send your enquiry. Please try again.";
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = 'Send enquiry <span aria-hidden="true">→</span>';
    }
  });
}
