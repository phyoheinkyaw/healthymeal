<!DOCTYPE html>
<html>
<head>
    <title>Test Order Details API</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Order Details API Test</h1>
    <div id="result"></div>
    
    <script>
        // Test the API endpoint
        fetch('api/orders/get_details.php?id=1')
            .then(response => response.json())
            .then(data => {
                console.log(data);
                document.getElementById('result').textContent = 'API responded successfully! Check console for details.';
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('result').textContent = 'API Error: ' + error.message;
            });
    </script>
</body>
</html> 