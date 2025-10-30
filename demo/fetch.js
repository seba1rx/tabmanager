fetch("handler.php")
    .then(response => {
        // Check if the request was successful (status code 200-299)
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // Parse the response body as JSON
        return response.json();
    })
    .then(data => {
        // Log the fetched JSON data
        console.log('Fetched data:', data);
        // You can now work with the 'data' object
        // For example, display it on a webpage
        // document.getElementById('output').textContent = JSON.stringify(data, null, 2);
    })
    .catch(error => {
        // Handle any errors that occurred during the fetch operation
        console.error('Error fetching data:', error);
    });


async function postData(data) {
    try {
        const response = await fetch("handler.php", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data), // Convert the JavaScript object to a JSON string
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
        }

        const responseData = await response.json(); // Parse the JSON response
        console.log('Success:', responseData);
        return responseData;
    } catch (error) {
        console.error('Error during fetch operation:', error);
    }
}