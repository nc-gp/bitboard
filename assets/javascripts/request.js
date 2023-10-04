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

    xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (callback !== null) {
                callback(null, xhr.responseText);
            }
        } else {
            if (callback !== null) {
                callback(`Request failed with status ${xhr.status}`, null);
            }
        }
    };

    xhr.onerror = () => {
        if (callback !== null) {
            callback('Request failed due to a network error', null);
        }
    };

    xhr.open(method, url);
    xhr.send(data);
};