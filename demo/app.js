async function addData() {
    try {
        const response = await fetch('addData.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        console.log('Server response:', result);
        document.getElementById('session_data').innerHTML = `<pre>${JSON.stringify(result, undefined, 2)}</pre>`;
    } catch (error) {
        console.error('Error sending data:', error);
    }
}


function reset() {
    window.location.href = "terminate.php";
}