function startLiveStreamPoller(url) {
    $.ajax(url).then(function (response) {
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

function fetchRocketChatAuthToken(url) {

    var frame = document.getElementById('rocketChatIframe');
    frame.style.visibility = 'hidden';

    setTimeout(function() {
        $.ajax(url).then(function (response) {
            setTimeout(function() {

                if (!response.authToken) {
                    return;
                }

                var token = response.authToken;
                frame.style.visibility = 'visible';

                var data = {
                    externalCommand: 'login-with-token',
                    token: token
                };

                frame.contentWindow.postMessage(data, '*');

            }, 2000);
        });
    }, 2000);
}
