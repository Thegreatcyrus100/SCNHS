// Updates strands based on track selected
function updateStrands() {
    var track = document.getElementById("track").value;
    var strandDropdown = document.getElementById("strand");

    // Clear previous strand options
    strandDropdown.innerHTML = '<option value="">-- Select Strand --</option>';

    if (track === "Academic") {
        strandDropdown.innerHTML += `
            <option value="stem">STEM (Science, Technology, Engineering & Math)</option>
            <option value="abm">ABM (Accountancy, Business, and Management)</option>
            <option value="humss">HUMSS (Humanities & Social Sciences)</option>
            <option value="gas">GAS (General Academic Strand)</option>
        `;
    } else if (track === "TVL") { // Ensure track value matches the expected case
        strandDropdown.innerHTML += `
            <option value="css">Computer Systems Servicing (CSS)</option>
            <option value="eim">Electrical Installation and Maintenance (EIM)</option>
            <option value="smaw">Shielded Metal Arc Welding (SMAW)</option>
            <option value="fbs">Food and Beverage Services (FBS)</option>
            <option value="bpp">Bread and Pastry Production (BPP)</option>
            <option value="caregiving">Caregiving</option>
        `;
    }
}


// Toggles the visibility of the previous education section
function toggleEducationInfo() {
    var select = document.getElementById("is_returning_or_transfer");
    var section = document.getElementById("previous_education_section");
    section.style.display = select.value === "yes" ? "block" : "none";
}

// Auto-format school year input — wrapped in DOMContentLoaded with null guard
document.addEventListener('DOMContentLoaded', function () {
    var schoolYearField = document.getElementById('school_year');
    if (!schoolYearField) return; // Only run on pages that have this field

    schoolYearField.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
        if (value.length >= 4) {
            const firstYear = value.slice(0, 4);
            const secondYear = parseInt(firstYear) + 1;
            e.target.value = `${firstYear}-${secondYear}`;
        }
    });

    schoolYearField.addEventListener('blur', function(e) {
        const value = e.target.value;
        if (value) {
            const years = value.split('-');
            if (years.length === 2) {
                const firstYear = parseInt(years[0]);
                const secondYear = parseInt(years[1]);
                if (secondYear !== firstYear + 1) {
                    alert('Invalid school year format. The second year should be the next year.');
                    e.target.value = '';
                }
            }
        }
    });
});
