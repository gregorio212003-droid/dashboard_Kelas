
const menuToggle = document.getElementById("menu-toggle");
const sidebar = document.getElementById("sidebar");

if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("show");
    });
}

// Grafik perkembangan nilai

const chartElement = document.getElementById("gradeChart");

if (chartElement && typeof Chart !== "undefined") {

    new Chart(chartElement, {
        type: "bar",

        data: {
            labels: chartLabels,

            datasets: [{
                label: "Rata-rata Nilai",

                data: chartValues,

                backgroundColor: "#3b82f6",

                borderRadius: 6,

                maxBarThickness: 45
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            },

            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}
