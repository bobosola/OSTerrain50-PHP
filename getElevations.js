
// Minimal demo to request OS Terrain 50 elevation data

const locSnowdon = {easting: 260993, northing: 354380, elevation: null};
const locLuccombe = {easting: 456542, northing: 78503, elevation: null};

document.getElementById("btnElevations").addEventListener('click', event => {

    let locUser1 = {
        easting: document.getElementById("locUser1e").value, 
        northing: document.getElementById("locUser1n").value, 
        elevation: null
    };  
    let locUser2 = {
        easting: document.getElementById("locUser2e").value, 
        northing: document.getElementById("locUser2n").value, 
        elevation: null
    };

    // Array to hold all the location objects to be processed
    let coords = [];

    // Add a location to the array if its box is ticked
    if (document.getElementById("cbSnowdon").checked) {coords.push(locSnowdon);}
    if (document.getElementById("cbLuccombe").checked) {coords.push(locLuccombe);}    
    if (document.getElementById("cbUser1").checked) {coords.push(locUser1);}
    if (document.getElementById("cbUser2").checked) {coords.push(locUser2);}

    if (coords.length == 0){
        document.getElementById('results').innerHTML = "Please select at least one location."
        return;
    }

    // For demo purposes only, measure how long it takes to retrieve the elevations
    const tStart = performance.now(); 

    // Post a JSON string request as: {locations: [array of locations], doInfills: bool}
    fetch('getElevations.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({locations: coords, doInfills: document.getElementById("infills").checked}),
    })
    .then(response => response.json())
    .then(function (retval) {

        // Do something useful with retval here

        // The return value is the same array of location objects but with the elevations populated
        // e.g. [{easting:216692, northing:771274, elevation:1345}, ...] 
        // so the first location's elevation value is accessed as: retval[0].elevation

        // The remaining code here is just for the demo
        const tEnd = performance.now();
        // Regex hack with JSON.stringify() to output retval for display purposes
        // (it removes the quotes around names added by JSON.stringify())
        let jsString = JSON.stringify(retval).replace(/"/g, '');

        let output = `${retval.length} locations returned in ${Math.round((tEnd - tStart))}ms:<br>${jsString}`;
        document.getElementById('results').innerHTML = output;
    })	
    .catch(error => {
        console.log('getElevations ' + error);
    });
});