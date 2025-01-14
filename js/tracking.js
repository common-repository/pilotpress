// -- MoonRay Tracking Module

// Settings
var _mri = '',
    _mrsess_ = '',
    _mr_cid = '';
var _mrd = document,
    _mrl = _mrd.location.host + _mrd.location.pathname,
    _mrct = new Date();
var _mr_ex = 'expires=Sun, 18 Jan 2038 00:00:00 GMT';
var _linktrack = '/trackThrough.php';
var _mr_title = document.title;

if (typeof(_mr_domain) === 'undefined') {
    _mr_domain = 'tracking.ontraport.com';
}

var _mrl_internal_url = '';
var _mrl_internal_domain = '';

function mrSetupActual(url, domain) {
    _mrl_internal_url = url;
    _mrl_internal_domain = domain;
}

function mrtracking(mriOverRide) {
    var _mrid = _mri,
        _mrlv = '',
        _mrref = '',
        _mrafft_ = '',
        _mraff_ = '',
        _mrsess_ = '',
        _mr_gets = '',
        _mr_ctime = '',
        _mrq = '';
    
    var _readParams = function() {
        if (mriOverRide) {
            _mrid = mriOverRide;
        }
        if (_mrl_internal_domain != '' && _mrl_internal_domain != document.location.hostname) {
            // URL is not the same as the trackable one, and we were passed the original url so use that.
            // This happens for wordpress-hosted ontrapages. Override the llc and l parameters.
            _mrq = 'mid=' + _mrid + '&llc=' + _escapeT(_mrl_internal_url);
            _mrl = _mrl_internal_url;
        } else {
            _mrq = 'mid=' + _mrid + '&llc=' + _escapeT(document.location.href);
        }
        _mrlv = gC('lastvisit');
        _mrref = gC('ref_');
        _mrafft_ = gC('afft_');
        _mraff_ = gC('aff_');
        _mrsess_ = gC('sess_');

        _mr_gets = parseGetVars();
        _mr_ctime = (_mrct.getTime() - _mrct.getMilliseconds()) / 1000;

        if (!_mrsess_) {
            _mrsess_ = genmrSess(20);
            _mrq = _mrq + '&first_visit=1';
            _mrd.cookie = 'sess_=' + _mrsess_ + ';' + _mr_ex + ';' + 'path=/';

            _mrq = _mrq + '&referral_page=' + encodeURIComponent(document.referrer);
            if (_mrq.length <= 200) {
                _mrd.cookie = 'referral_page=' + document.referrer + ';' + _mr_ex + ';' + 'path=/';
            } else {
                _mrq = 'mid=' + _mrid + '&first_visit=1';
            }

        }

        if (_mr_gets.mr_aff) {
            _mrd.cookie = 'aff_' + '=' + _mr_gets.mr_aff + ';' + _mr_ex + ';' + 'path=/';
            _mrq = _mrq + '&aff=' + _mr_gets.mr_aff;
        } else if (_mr_gets.ref) {
            _mrd.cookie = 'aff_' + '=' + _mr_gets.ref + ';' + _mr_ex + ';' + 'path=/';
            _mrq = _mrq + '&aff=' + _mr_gets.ref;
        } else if (_mr_gets.orid) {
            _mrd.cookie = 'aff_' + '=' + _mr_gets.orid + ';' + _mr_ex + ';' + 'path=/';
            _mrq = _mrq + '&aff=' + _mr_gets.orid;
        }


        var cookie_gets = ['opid', 'sid', 'oprid'];
        for (var i = 0; i < cookie_gets.length; i++) {
            if (_mr_gets[cookie_gets[i]]) {
                _mrd.cookie = cookie_gets[i] + '=' + _mr_gets[cookie_gets[i]] + ';' + _mr_ex + ';' + 'path=/';
                _mrq = _mrq + '&' + cookie_gets[i] + '=' + _mr_gets[cookie_gets[i]];
            }
        }

        cookie_gets = ['utm_source', 'utm_medium', 'utm_term', 'utm_content', 'utm_campaign'];
        var has_utm_gets = false;

        for (var i = 0; i < cookie_gets.length; i++) {
            if (_mr_gets[cookie_gets[i]]) {
                has_utm_gets = true;
                break;
            }
        }

        if (has_utm_gets) {
            for (var i = 0; i < cookie_gets.length; i++) {
                var term = _mr_gets[cookie_gets[i]] || '';
                _mrd.cookie = cookie_gets[i] + '=' + term + ';' + _mr_ex + ';' + 'path=/';
                _mrq = _mrq + '&' + cookie_gets[i] + '=' + term;
            }
        }

        _mrq = _mrq + '&s=' + _mrsess_ + '&l=' + _mrl + '&ti=' + _mr_title;
        if (_mrref && !_mr_gets.sig) {
            if ((_mrlv + 3600) < _mr_ctime) {
                _mrq = _mrq + '&' + 't=' + _mrref;
            } else {
                _mrq = _mrq + '&' + 'r=1&t=' + _mrref;
            }
        }

        if (_mr_cid = gC('contact_id')) {
            _mrq = _mrq + '&c=' + _mr_cid;
            _mrd.cookie = 'contact_id' + '=' + _mr_cid + ';' + _mr_ex + ';' + 'path=/';
        }

        if ((parseInt(_mrlv) + 3600) > _mr_ctime) {
            _mr_vid = gC('vid');
            if (_mr_vid) {
                _mrq = _mrq + '&vid=' + _mr_vid;
            }
        } else {
            _mrd.cookie = 'vid' + '=' + '' + ';' + _mr_ex + ';' + 'path=/';
        }

        if (_mr_gets.sig) {
            _mrd.cookie = 'ref_' + '=' + _mr_gets.sig + ';' + _mr_ex + ';' + 'path=/';
            if (_mrlv) {
                if ((parseInt(_mrlv) + 3600) < _mr_ctime) {
                    _mrq = _mrq + '&' + 't=' + _mr_gets.sig;
                } else {
                    _mrq = _mrq + '&' + 't=' + _mr_gets.sig + '&r=1';
                }
            } else {
                _mrq = _mrq + '&' + 't=' + _mr_gets.sig;
            }
        }
        // Set cookie for lastvisit to the current time
        _mrd.cookie = 'lastvisit' + '=' + _mr_ctime + ';' + _mr_ex + ';' + 'path=/';

        // Grab Google gclid and gid
        if (window.ga) {
            ga(function() {
                var trackers = ga.getAll();
                trackers.forEach(function(tracker) {
                    _mrq = _mrq + '&gcid=' + tracker.get('clientId');
                });
            });
        }
    }

    _readParams();

    var sc = document.createElement('script');

    var _onload = function() {
        for (var i = 0; i < document.forms.length; i++) {
            if (!document.forms[i].hasAttribute('data-opf-watch-submit') && document.forms[i].uid) {
                if (document.forms[i].elements['unique' + document.forms[i].uid.value]) {
                    _mrq = _mrq + '&forms[' + document.forms[i].uid.value + ']=' + document.forms[i].elements['unique' + document.forms[i].uid.value].value;
                } else {
                    _mrq = _mrq + '&forms[' + document.forms[i].uid.value + ']=0';
                }
            }
        }
        var meta = document.getElementsByTagName('meta');
        var is_unique = '0';
        for (var i = 0; i < meta.length; i++) {
            if (meta[i].getAttribute('name') === 'unique') {
                is_unique = meta[i].getAttribute('value');
            }
        }
        _mrq = _mrq + '&is_unique=' + is_unique;
        sc.src = 'https://' + _mr_domain + '/track.php?' + encodeURI(_mrq);
        document.getElementsByTagName('head')[0].appendChild(sc);
    };

    var replaceHistoryFunction = function(type) {
		var orig = history[type];
		history[type + 'IsSet'] = true;
		return function() {
			var rv = orig.apply(this, arguments);
			var e = new Event(type);
			e.arguments = arguments;
			window.dispatchEvent(e);
			return rv;
		};
	};

	if( !history['pushStateIsSet'] ) {
		history.pushState = replaceHistoryFunction('pushState');

		window.addEventListener('pushState', function(e) {
			//without a delay, we will incorrectly track the page we're on, not the page we're going to.
			setTimeout( function() {
				_readParams();
				_onload();
			}, 0);
		});
	}


    if (_mr_gets.gclid !== undefined && !gC('_op_gclid')) {
        _mrd.cookie = '_op_gclid' + '=' + _mr_gets.gclid + ';' + _mr_ex + ';' + 'path=/';
        _mrq = _mrq + '&gclid=' + _mr_gets.gclid;
    }

    //check if window.onload has already fired or not.
    if (document.readyState === 'complete') {
        _onload();
    } else if (window.addEventListener) {
        window.addEventListener('load', _onload, true);
    } else {
        window.attachEvent('onload', _onload);
    }
}

function gC(c_name) {
    var i, x, y, ARRcookies = document.cookie.split(';');
    for (i = 0; i < ARRcookies.length; i++) {
        x = ARRcookies[i].substr(0, ARRcookies[i].indexOf('='));
        y = ARRcookies[i].substr(ARRcookies[i].indexOf('=') + 1);
        x = x.replace(/^\s+|\s+$/g, '');
        if (x == c_name) {
            return decodeURI(y);
        }
    }
}

function parseGetVars() {
    var encodedURI = window.location.search.substring(1).replace(/\+/g, ' ');

    var qString = decodeURI(encodedURI);
    var mr_dv = new Array();
    var pairs = qString.split(/\&/);
    var array_length = pairs.length;
    if (array_length > 0) {
        for (i = 0; i < array_length; i++) {
            if (pairs[i]) {
                var nameVal = pairs[i].split(/\=/);
                mr_dv[nameVal[0]] = nameVal[1];
            }
        }
    }
    return mr_dv;
}

function genmrSess(length) {
    session = '';
    possible = '0123456789bcdfghjkmnpqrstvwxyz';

    for (var i = 0; i < length; ++i) {
        session += possible.substr(Math.floor(Math.random() * possible.length), 1);
    }

    return session;
}

function _escapeT(str) {
    return encodeURI(str).replace(/\+/g, '%2B');
}

function _mrGetLinkTo(Item) {
    if (Item.href != null) {
        return Item.href;
    } else if (Item.getAttribute('type') == 'submit') {
        return (Item.form.action.indexOf('form_processor.php') != -1) ? 'moonrayform:' + Item.form.uid.value : Item.form.action;
    } else {
        return Item.attributes.getNamedItem('onclick').value;
    }
}

function _sanitizeMrLink(link) {
    if (link.substr(0, 7).toLowerCase() == 'http://') {
        link = link.substr(7);
    }
    if (link.substr(0, 8).toLowerCase() == 'https://') {
        link = link.substr(8);
    }
    if (link.substr(0, 4).toLowerCase() == 'www.') {
        link = link.substr(4);
    }
    return link;
}

function _mrScanLinks() {
    var Items = document.getElementsByTagName('*');
    var LinkId, LinkAlias;
    var linkCount = 0;
    for (var i = 0; i < Items.length; i++) {
        if ((Items[i].href == null || Items[i].getAttribute('href', 2) == '') && Items[i].onclick == null && Items[i].type != 'submit') {
            continue;
        }
        linkCount++;
        LinkId = -1;
        LinkAlias = '';

        if (Items[i].getAttribute('mtrack', 2)) {
            LinkAlias = Items[i].getAttribute('mtrack', 2);
        } else if (_mrTrackLinks[linkCount] != null) {
            if (_sanitizeMrLink(_mrGetLinkTo(Items[i])) == _sanitizeMrLink(_mrTrackLinks[linkCount].link)) {
                LinkId = _mrTrackLinks[linkCount].id;
            }
        }
        if (LinkId != -1 || LinkAlias != '') {
            if (Items[i].addEventListener) {
                Items[i].addEventListener('click', _mrTrackLink, false);
            } else {
                Items[i].attachEvent('onclick', _mrTrackLink);
            }
            Items[i].linkTrackId = (LinkId != -1) ? LinkId : '';
        }
    }
}

function _mrTrackLink(event) {
    if (event.target) {
        var SrcObj = event.target;
    } else {
        var SrcObj = event.srcElement;
    }
    while (SrcObj.linkTrackId == null && !SrcObj.getAttribute('mtrack', 2)) {
        SrcObj = SrcObj.parentNode;
    }

    var clickTrack = _mr_domain + _linktrack +
        '?lid=' + _escapeT(SrcObj.linkTrackId) +
        '&contact_id=' + _mr_cid +
        '&session_id=' + _mrsess_ +
        '&mri=' + _mri +
        ((SrcObj.getAttribute('mtrack', 2)) ? '&mtrack=' + _escapeT(SrcObj.getAttribute('mtrack', 2)) + '&uri=' + _escapeT(document.location.href) : '');
    var Ajaxor = _mrReturnXmlHttpObject();
    Ajaxor.open('GET', clickTrack, true);
    Ajaxor.send(null);
}

function _mrReturnXmlHttpObject() {
    var xmlhttp = false;

    if (!xmlhttp && typeof XMLHttpRequest != 'undefined') {
        try {
            xmlhttp = new XMLHttpRequest();
        } catch (e) {
            xmlhttp = false;
        }
    }
    if (!xmlhttp && window.createRequest) {
        try {
            xmlhttp = window.createRequest();
        } catch (e) {
            xmlhttp = false;
        }
    }
    if (!xmlhttp) {}
    return xmlhttp;
}