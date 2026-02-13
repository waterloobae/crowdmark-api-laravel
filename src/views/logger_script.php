<script data-status='logger'>
    const content = `{_LoggerDiv}`;
    const path = '{_WebRootPath}';
    var logger = document.getElementById('crowdmarkdashboard_logger');
    if (!logger) {
        document.write(content);
        var logger = document.getElementById('crowdmarkdashboard_logger');
    }
    function updateLoggerMessage() {
        fetch( path + '/LoggerMessage.php')
            .then(response => response.json()) // Convert response to JSON
            .then(data => {
                let error = data.error_msg;
                let warning = data.warning_msg;
                let info = data.info_msg;
                if (error !== 'NA') {
                    logger.innerHTML = '<div style=\"color: red;\">Error: ' + error + '</div>';
                }
                if (warning !== 'NA') {
                    logger.innerHTML = '<div style=\"color: orange;\">Warning: ' + warning + '</div>';
                }
                if (info !== 'NA') {
                    logger.innerHTML = '<div style=\"color: blue;\">Info: ' + info + '</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching logger:', error);
                logger.innerHTML = '<div style=\"color: red;\">Error fetching logger.</div>';
            });
    }
    // Run `updateLoggerMessage` every second
    // This is commented out to prevent continuous updates, uncomment if needed
    // let updateLoggerMessageInterval = setInterval(updateLoggerMessage, 1000);
</script>