/**
 * Credits: bitpaper
 * License: MIT
 * 
 * @ 2023
 */

/**
 * Function makes an API call to the specified URL with the method.
 * @param {String}      method      Method request type
 * @param {String}      url         URL to call
 * @param {String}      data        Data to send to the API
 * @param {Function}    callback    Callback returns data or error from the call
 */
const req = (method, url, data = null, callback = null) => {
    const xhr = new XMLHttpRequest();
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (callback !== null) {
                callback(null, xhr.responseText);
            }
        } else {
            if (callback !== null) {
                let message = `Request failed with status ${xhr.status}`;

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response !== null && response.data !== null && response.data.message) {
                        message = response.data.message;
                    }
                } catch (error) {
                    // Keep the HTTP status message when the body is not JSON.
                }

                callback(message, null);
            }
        }
    };

    xhr.onerror = () => {
        if (callback !== null) {
            callback('Request failed due to a network error', null);
        }
    };

    xhr.open(method, url);

    if (csrfMeta !== null) {
        xhr.setRequestHeader('X-CSRF-Token', csrfMeta.getAttribute('content'));
    }

    xhr.send(data);
};