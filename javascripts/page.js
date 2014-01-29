window.onload = function () {
    var toc = "";
    var level = 0;

    document.getElementById("contents").innerHTML =
    	document.getElementById("contents").innerHTML.replace(
    		/<h([\d])>([^<]+)<\/h([\d])>/gi,
    		function (str, openLevel, titleText, closeLevel) {
    			if (openLevel != closeLevel) {
    				return str;
    			}

    			if (openLevel > level) {
    				toc += (new Array(openLevel - level + 1)).join("<ul class=\""+(openLevel!=1&&openLevel!=2?"indent":"")+"\">");
    			} else if (openLevel < level) {
    				toc += (new Array(level - openLevel + 1)).join("</ul>");
    			}

    			level = parseInt(openLevel);
//console.log('here');
    			var anchor = titleText.replace(/ /g, "-");
	    		toc += "<li><a href=\"#" + anchor.toLowerCase() + "\">" + titleText
	    			+ "</a></li>";

    			return "<h" + openLevel + "><a name=\"" + anchor + "\">"
    				+ titleText + "</a></h" + closeLevel + ">";
    		}
    	);

    if (level) {
    	toc += (new Array(level + 1)).join("</ul>");
    }

    document.getElementById("toc").innerHTML += toc;
};

fixScale = function(doc) {

	var addEvent = 'addEventListener',
	    type = 'gesturestart',
	    qsa = 'querySelectorAll',
	    scales = [1, 1],
	    meta = qsa in doc ? doc[qsa]('meta[name=viewport]') : [];

	function fix() {
		meta.content = 'width=device-width,minimum-scale=' + scales[0] + ',maximum-scale=' + scales[1];
		doc.removeEventListener(type, fix, true);
	}

	if ((meta = meta[meta.length - 1]) && addEvent in doc) {
		fix();
		scales = [.25, 1.6];
		doc[addEvent](type, fix, true);
	}

};