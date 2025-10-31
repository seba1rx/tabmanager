async function addData() {
    try {
        const response = await fetch("addData.php", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
        }

        // const responseData = await response.json(); // Parse the JSON response
        const responseData = await response; // Parse the JSON response
        let pretty = JSON.stringify(responseData, null, 4);
        console.log('Success! new data added', responseData);

        document.getElementById('session_data').innerHTML = responseData;
        // document.getElementById('session_data').textContent = responseData;

    } catch (error) {
        console.error('Error during fetch operation:', error);
    }
}

function reset()
{
    window.location.href = "terminate.php";
}