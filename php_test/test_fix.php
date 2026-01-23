<!DOCTYPE html>
<html>
<head>
    <title>Multiplayer Fix Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .test-result { margin: 10px 0; padding: 10px; border: 1px solid #ccc; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔧 Multiplayer Combat Polling - Test & Diagnostics</h1>
    
    <h2>Tests</h2>
    <div id="results"></div>
    
    <h2>Actions</h2>
    <button onclick="testAPIEndpoint()">Test API Endpoint</button>
    <button onclick="cleanupBrokenMatch()">Cleanup Broken Match</button>
    <button onclick="location.href='index.php'">Back to Menu</button>
    
    <script>
        const MATCH_ID = 'match_69736ec683576';
        
        async function testAPIEndpoint() {
            const results = document.getElementById('results');
            results.innerHTML = '<p>Testing API endpoint...</p>';
            
            try {
                const response = await fetch('api.php?action=poll_status&match_id=' + MATCH_ID);
                const contentType = response.headers.get('content-type');
                const text = await response.text();
                
                let html = '<div class="test-result">';
                html += '<strong>API Response Test:</strong><br>';
                html += 'Status: ' + response.status + '<br>';
                html += 'Content-Type: ' + contentType + '<br>';
                html += 'Response Length: ' + text.length + ' bytes<br>';
                
                try {
                    const data = JSON.parse(text);
                    html += '<p class="success">✓ Valid JSON response received!</p>';
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } catch (e) {
                    html += '<p class="error">✗ INVALID JSON! ' + e.message + '</p>';
                    html += '<p>First 500 chars:</p>';
                    html += '<pre>' + text.substring(0, 500) + '</pre>';
                }
                
                html += '</div>';
                results.innerHTML = html;
            } catch (err) {
                results.innerHTML = '<div class="test-result error">✗ Fetch error: ' + err.message + '</div>';
            }
        }
        
        async function cleanupBrokenMatch() {
            if (confirm('This will reset the broken match. Continue?')) {
                location.href = 'cleanup.php?match_id=' + MATCH_ID;
            }
        }
    </script>
</body>
</html>
