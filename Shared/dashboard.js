// Shared dashboard JS (Owner + Staff) — dati may sarili-sariling
// copy nito ang bawat page, ngayon dito na lang para hindi na
// mag-drift ang isa't isa.

// date & time
function updateDateTime() {

    const dateElement =
        document.getElementById("currentDate");

    const timeElement =
        document.getElementById("currentTime");

    if (!dateElement || !timeElement) {
        return;
    }

    const now = new Date();

    dateElement.textContent =
        now.toLocaleDateString(
            "en-PH",
            {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric"
            }
        );

    timeElement.textContent =
        now.toLocaleTimeString(
            "en-PH",
            {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hour12: true
            }
        );
}

updateDateTime();

setInterval(
    updateDateTime,
    1000
);


// calendar date format (YYYY-MM-DD)

function formatDate(
    year,
    month,
    day
) {

    return (
        year +
        "-" +
        String(month + 1)
            .padStart(2, "0") +
        "-" +
        String(day)
            .padStart(2, "0")
    );
}
