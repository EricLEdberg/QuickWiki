<script>
function debugRetToQwiki() {
    var debugRetToQwiki = window.open("", "QWwindowName");
    if (debugRetToQwiki == null) {
        alert('windowQwiki open is null');
    } else {
        debugRetToQwiki.windowDebug = null;
        debugRetToQwiki.focus();
    }
};
function debugWindowClose() {
    debugRetToQwiki();
    window.close();
};
</script>

<html>
<div id="debugWindowHeader">
    <button id=buttonBackToQwiki onclick="debugRetToQwiki();"          >Back To QWiki</button>
    &nbsp;&nbsp;
    <button id=buttonWindowClose onclick="debugWindowClose();">Close Debug Window</button>
    <h2>QWiki Debug Window Log</h2>
</div>
<div id="debugWindowText"></div>
<div id="debugWindowFooter"></div>
</html>
