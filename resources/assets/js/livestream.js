function startLiveStreamPoller(url) {
    $.ajax(url).then(function (response) {
        console.log(response.wait);
        if (typeof (response.wait) !== 'undefined') {
            setTimeout(function() {
                startLiveStreamPoller(url);
            }, response.wait)
        }

        if (typeof (response.redirect) !== 'undefined') {
            window.location.replace(response.redirect);
        }
    });
}
