<form id="crowdmarkDashboardForm">
    <h1>Crowdmark Dashboard</h1>
    <p>Generate reports for Crowdmark courses.</p>
    <p>Reports are generated in the background and download links will be provided when ready.</p>
    <h2>1. Select Crowdmark Courses</h2>
    {_CHIPS}
    <div style="clear:both;"></div>
    <h2>2. Select Report to Generate</h2>
    {_ACTIONS}
    <input type="hidden" id="csrf_token" name="csrf_token" value="ABC123">
    <button>Generate Download Links</button>
    <div id="crowdmarkDashboard_response"></div>
    {_AJAXJS}
</form>
{_HEAD}