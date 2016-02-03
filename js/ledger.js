function makeHttpRequest(blah, callback_function, return_xml) {
	var url = "{$thisScript]";
	var http_request, response, i;

	var activex_ids = [
		'MSXML2.XMLHTTP.3.0',
		'MSXML2.XMLHTTP',
		'Microsoft.XMLHTTP'
	];

	if (window.XMLHttpRequest) { // Mozilla, Safari, IE7+...
		http_request = new XMLHttpRequest();
		if (http_request.overrideMimeType) {
			http_request.overrideMimeType('text/xml');
		}
	} else if (window.ActiveXObject) { // IE6 and older
		for (i = 0; i < activex_ids.length; i++) {
			try {
				http_request = new ActiveXObject(activex_ids[i]);
			} catch (e) {}
		}
	}

	if (!http_request) {
		alert('Unfortunatelly you browser doesn\'t support this feature.');
		return false;
	}

	http_request.onreadystatechange = function() {
		if (http_request.readyState !== 4) {
			// not ready yet
			return;
		}
		if (http_request.status !== 200) {
			// ready, but not OK
			alert('There was a problem with the request.(Code: ' +
					http_request.status + ')');
			return;
		}

		if (return_xml) {
			response = http_request.responseXML;
		} else {
			response = http_request.responseText;
		}
		/*
		callback_function(response);

		document.getElementById('ajax-response').innerHTML =
			  http_request.responseText;
		document.getElementById('ajax-response').className = "header1 red";
		if (http_request.responseText == "Email Sent") {
			setTimeout("hideit('email');",2000);
		}
		*/
	};

	http_request.open('GET', url, true);
	http_request.send(null);
	return false;
}
function hideit(element) {
	elem = document.getElementById(element);
	elem.className = "hideme";
	return false;
}
function confirmDeletion(id,descript) {
	delurl = "{$thisScript}?verb=delete&id=" + id;
	var response = confirm("You're deleting \"" + descript + "\".  OK?!?");
	if (response) {
		window.location = delurl;
	}
	else {
		return false;
	}
}
function editTrans(id,amount,descript) {
	clr = document.getElementById('clr');
	clr.checked = false;
	ID = document.getElementById('id');
	ID.value = id;
	desc = document.getElementById('desc');
	desc.value = descript;
	amt = document.getElementById('amt');
	amt.value = amount;
	elem = document.getElementById('editor');
	elem.className = 'showme';
}
