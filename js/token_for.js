function setCookie(cName, cValue, expDays) {
        let date = new Date();
        date.setTime(date.getTime() + (expDays * 24 * 60 * 60 * 1000));
        const expires = "expires=" + date.toUTCString();
        document.cookie = cName + "=" + cValue + "; " + expires + "; path=/" + '; Secure; SameSite=Strict';
}
function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}
$(document).ready(function(){
	if (window.Options && Options.get_tab('general') && window.jQuery) {
		Options.extend_tab('general', '<fieldset id="set-token"><legend>'+_('Token')+"</legend>"
			+_('Token:')+' <input type="text" id="token-input">'+
			'<button id="token-button">'+_('Set Token')+'</button>'+
			'<button id="tokenrm-button">'+_('Remove Token')+'</button><br/>'+
			'</fieldset>');
		$('#token-button').click(function() {
		setCookie('token', $('#token-input').val(), 30);
		document.location.reload();
		});
		$('#tokenrm-button').click(function() {
		setCookie('token', '', -1);
		document.location.reload();
		});
		$('#token-input').val(readCookie('token'));

	}
});
