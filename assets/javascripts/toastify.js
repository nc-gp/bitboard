const toast = (text, seconds = 5, rgb = '255, 53, 53') => {
    Toastify({
        text: text,
        duration: seconds * 1000,
        close: true,
        gravity: "bottom",
        position: "right",
        stopOnFocus: true,
        style: {
            background: "rgb(" + rgb + ")",
            boxShadow: "0 3px 6px -1px rgba(" + rgb + ",.12),0 10px 36px -4px rgba(" + rgb + ",.3)"
        }
    }).showToast();
}