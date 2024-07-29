"use strict";

const $document = $(document);
const $modal = $(".modal");
const $main = $("main");
const $countCartmenuUser = $(".menu-user");
const $loading = $("#loading");
const $countCart = $("#count-cart");
const startLoading = () => $loading.fadeIn();
const stopLoading = () => $loading.fadeOut();
$document.ready(function () {});
// $document.ajaxStart(() => {
//     startLoading();
// });
$document.ajaxStop(() => {
    // stopLoading();
    $("img").removeClass("lazy");
});
function formatCurrency(price) {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(price);
}

$document.on("click", "#btn-cart", function () {
    window.location.href = "/gio-hang";
});

$document.on("click", ".box-avatar-nav", function () {
    $countCartmenuUser.toggleClass("show");
});

$document.on("click", function (event) {
    if ($(event.target).closest(".menu-user, .box-avatar-nav").length === 0) {
        $countCartmenuUser.removeClass("show");
    }
});

function Toast({ message = "", type = "info", duration = 5000 }) {
    const notifications = document.querySelector(".notifications");
    if (notifications) {
        let newToast = document.createElement("div");
        const icons = {
            success: "fas fa-check-circle",
            info: "fas fa-exclamation-circle",
            warning: "fas fa-exclamation-triangle",
            error: "fas fa-times-circle",
        };
        const icon = icons[type];
        const delay = (duration / 1000).toFixed(2);
        newToast.style.animation = `show 0.5s ease 1 forwards, hide 0.5s ease 1 forwards ${delay}s`;

        newToast.innerHTML = `
        <div class="toast ${type} show">
        <i class="${icon}"></i>
        <div class="content">
        <span>${message}</span>
        </div>
        <i class="fas fa-times" onclick="(this.parentElement).remove()"></i>
        </div>`;
        notifications.appendChild(newToast);
        newToast.timeOut = setTimeout(() => newToast.remove(), duration + 500);
    }
}

const handleCountCart = () => {
    $.get("/carts/count").done((response) => {
        $countCart.text((response ?? 0) > 99 ? "99+" : response);
    });
};

function openModal({
    title = "",
    body = "",
    ok = "",
    cancel = "",
    size = "modal-lg",
    footer = true,
    icon = true,
}) {
    $modal.find(".modal-title").text(title);
    $modal.find(".modal-title").addClass("text-uppercase");
    $modal.find(".modal-title").addClass("text-center");
    $modal.find(".modal-title").addClass("w-100");
    $modal.find(".modal-body").empty().append(body);
    if (ok === "") {
        $modal.find(".btn-primary").addClass("d-none");
    } else {
        $modal.find(".btn-primary").text(ok);
    }
    if (cancel === "") {
        $modal.find(".btn-secondary").addClass("d-none");
    } else {
        $modal.find(".btn-secondary").text(cancel);
    }
    if (footer === false) {
        $modal.find(".modal-footer").addClass("d-none");
    }
    if (icon === false) {
        $modal.find(".btn-close").addClass("d-none");
    }
    $modal.find(".modal-dialog").addClass(size);
    $modal.modal("show");
}
const closeModal = () => {
    $modal.find(".modal-title").text("");
    $modal.find(".modal-body").empty();
    $modal.modal("hide");
};

$modal.on("click", ".btn-dismiss, .btn-close", closeModal);

const spanSpinner = () => {
    return `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>`;
};
