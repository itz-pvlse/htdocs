<?php require_once 'config/db.php'; ?>
<?php include 'includes/header.php'; ?>
<style>
#loadingSpinner {
    display: none;
    text-align: center;
    margin: 20px 0;
}

#loadingSpinner .spinner-border {
    width: 3rem;
    height: 3rem;
}

.spinner-container {
  display: none;
  text-align: center;
  margin: 20px 0;
}

.custom-wheel {
  border: 6px solid #f3f3f3;
  border-top: 6px solid #007bff; /* Blue */
  border-radius: 50%;
  width: 50px;
  height: 50px;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

</style>

            <!-- Page Content-->
           <section class="py-5">
    <div class="container px-5">
        <h1 class="fw-bolder fs-5 mb-4"></h1>
        <div class="card border-0 shadow rounded-3 overflow-hidden">
            <div class="card-body p-0">
                <div class="row gx-0">
                    <!-- Left side content -->
                    <div class="col-lg-6 col-xl-5 py-lg-5">
                        <div class="p-4 p-md-5">
                            <div class="badge bg-primary bg-gradient rounded-pill mb-2">WHAT WE ARE ABOUT</div>
                            <div class="h2 fw-bolder">Want to know about your current or dream car.</div>
                            <p>AutoLog is Kenya’s first car history and maintenance platform that helps you verify vehicle information using the Plate Number. Whether you're buying, selling, or just curious about your car, AutoLog provides trusted data on ownership history, maintenance records, and past accidents—giving you peace of mind and helping you make informed decisions.</p>


                        
    <h2>Search Vehicle by Plate Number</h2>
	

    <!-- Search Form -->
    <form class="input-group mt-4" onsubmit="return searchVehicle(event);">
        <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Enter Plate Number..." aria-label="Plate Number" />
        <div class="input-group-append">
            <button type="submit" class="btn btn-primary btn-lg">FIND DETAILS</button>
        </div>
    </form>
<div id="loadingSpinner" class="spinner-container">
  <div class="custom-wheel"></div>
  <div class="mt-2">Loading vehicle data...</div>
</div>


    <!-- Search Result -->
    <div id="searchResult" class="mt-4"></div>

    <script>
        function searchVehicle(event) {
            event.preventDefault(); // Stop form reload

            const plate = document.getElementById('searchInput').value.trim();
            const resultDiv = document.getElementById('searchResult');
			const spinner = document.getElementById("loadingSpinner");

            if (plate === "") {
                resultDiv.innerHTML = "<p style='color:red;'>Please enter a Plate Number.</p>";
                return;
            }

// Show spinner
    spinner.style.display = "block";
    


            fetch("search_vehicle.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "plate_no=" + encodeURIComponent(plate)
            })
            .then(response => response.text())
            .then(data => {
				spinner.style.display = "none"; // hide spinner
                resultDiv.innerHTML = data;
            })
            .catch(error => {
				spinner.style.display = "none"; // hide spinner
                resultDiv.innerHTML = "<p style='color:red;'>Error occurred while searching.</p>";
                console.error("Error:", error);
            });
        }
    </script>



<div class="d-flex flex-wrap gap-2 mt-4">
  <a href="#images" class="btn btn-outline-green rounded-pill">Recorded Images</a>
  <a href="#damage" class="btn btn-outline-green rounded-pill">Damage</a>
  <a href="#theft" class="btn btn-outline-green rounded-pill">Theft Records</a>
  <a href="#mileage" class="btn btn-outline-green rounded-pill">Mileage Rollbacks</a>
  <a href="#specs" class="btn btn-outline-green rounded-pill">Specs & Equipment</a>
  <a href="#emissions" class="btn btn-outline-green rounded-pill">Emission Taxes</a>
  <a href="#value" class="btn btn-outline-green rounded-pill">Market Value</a>
  <a href="#safety" class="btn btn-outline-green rounded-pill">Safety</a>
  <a href="#finance" class="btn btn-outline-green rounded-pill">Financial Restrictions</a>
  <a href="#disasters" class="btn btn-outline-green rounded-pill">Natural Disasters</a>
</div>

                            
                        </div>
                    </div>

                    <!-- Right side image -->
                    <div class="col-lg-6 col-xl-7 d-flex align-items-center justify-content-center">
                        <div class="bg-featured-blog">
                            <img class="card-img-top" src="assets/autolog2.png" alt="My Photo"
                                 style="width: 460px; height: 460px; object-fit: cover;" />
                        </div>

                    </div>
					
					

                </div>
            </div>
        </div>
    </div>
</section>




            <section class="py-5 bg-light">
                <div class="container px-5">
                    <div class="row gx-5">
                        <div class="col-xl-8">
                            <h2 class="fw-bolder fs-5 mb-4">News</h2>
                            <!-- News item-->
                            <div class="mb-4">
                                <div class="small text-muted">May 12, 2025</div>
                                <a class="link-dark" href="#!"><h3>Autolog launches more services on their platform.</h3></a>
                            </div>
                            <!-- News item-->
                            <div class="mb-5">
                                <div class="small text-muted">May 5, 2025</div>
                                <a class="link-dark" href="#!"><h3>Autolog update on the release of new features on the webapp.</h3></a>
                            </div>
                            <!-- News item-->
                            <div class="mb-5">
                                <div class="small text-muted">Apr 21, 2025</div>
                                <a class="link-dark" href="#!"><h3>More garages get enrolled on the Autolog platform making car owners happier.</h3></a>
                            </div>
                            <div class="text-end mb-5 mb-xl-0">
                                <a class="text-decoration-none" href="#!">
                                    More news
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card border-0 h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex h-100 align-items-center justify-content-center">
                                        <div class="text-center">
                                            <div class="h6 fw-bolder">Contact</div>
                                            <p class="text-muted mb-4">
                                                For press inquiries, email us at
                                                <br />
                                                <a href="#!">press@domain.com</a>
                                            </p>
                                            <div class="h6 fw-bolder">Follow us</div>
                                            <a class="fs-5 px-2 link-dark" href="#!"><i class="bi-twitter"></i></a>
                                            <a class="fs-5 px-2 link-dark" href="#!"><i class="bi-facebook"></i></a>
                                            <a class="fs-5 px-2 link-dark" href="#!"><i class="bi-linkedin"></i></a>
                                            <a class="fs-5 px-2 link-dark" href="#!"><i class="bi-youtube"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Blog preview section-->
            <section class="py-5">
                <div class="container px-5">
                    <h2 class="fw-bolder fs-5 mb-4">More services</h2>
                    <div class="row gx-5">
                        <div class="col-lg-4 mb-5">
                            <div class="card h-100 shadow border-0">
                                <img class="card-img-top" src="assets\newcars.jpeg" alt="..." />
                                <div class="card-body p-4">
                                    <div class="badge bg-primary bg-gradient rounded-pill mb-2">Market place</div>
                                    <a class="text-decoration-none link-dark stretched-link" href="#!"><div class="h5 card-title mb-3">New Cars </div></a>
                                    <p class="card-text mb-0">New cars freshly imported from build countries. Are you looking to buy newly imported vehicles.Well we partner you with the ideal showroom.</p>
                                </div>
                                <div class="card-footer p-4 pt-0 bg-transparent border-top-0">
                                    <div class="d-flex align-items-end justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img class="rounded-circle me-3" src="https://dummyimage.com/40x40/ced4da/6c757d" alt="..." />
                                            <div class="small">
                                                <div class="fw-bold">cars & cars</div>
                                                <div class="text-muted">March 12, 2025 &middot; ready for viewing</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-5">
                            <div class="card h-100 shadow border-0">
                                <img class="card-img-top" src="assets\showrooms.jpeg" alt="..." />
                                <div class="card-body p-4">
                                    <div class="badge bg-primary bg-gradient rounded-pill mb-2">Brokers</div>
                                    <a class="text-decoration-none link-dark stretched-link" href="#!"><div class="h5 card-title mb-3">Showrooms</div></a>
                                    <p class="card-text mb-0">You are not sure of where to start with you purchase of that dream car.We offer featured showrooms and the right car dealers to assist you with making that decision with exactly that amount you had in your budget.</p>
                                </div>
                                <div class="card-footer p-4 pt-0 bg-transparent border-top-0">
                                    <div class="d-flex align-items-end justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img class="rounded-circle me-3" src="https://dummyimage.com/40x40/ced4da/6c757d" alt="..." />
                                            <div class="small">
                                                <div class="fw-bold">Josiah Barclay</div>
                                                <div class="text-muted">March 25, 2025 &middot; ready for viewing</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-5">
                            <div class="card h-100 shadow border-0">
                                <img class="card-img-top" src="assets\insights.jpeg" alt="..." />
                                <div class="card-body p-4">
                                    <div class="badge bg-primary bg-gradient rounded-pill mb-2">Insights</div>
                                    <a class="text-decoration-none link-dark stretched-link" href="#!"><div class="h5 card-title mb-3">Know your Cars.</div></a>
                                    <p class="card-text mb-0">We give you the knowledge you will need for your car.The more you know about your car and its specifications ,the easier it becomes to manage it.</p>
                                </div>
                                <div class="card-footer p-4 pt-0 bg-transparent border-top-0">
                                    <div class="d-flex align-items-end justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img class="rounded-circle me-3" src="https://dummyimage.com/40x40/ced4da/6c757d" alt="..." />
                                            <div class="small">
                                                <div class="fw-bold">Evelyn Martinez</div>
                                                <div class="text-muted">April 2, 2025 &middot; ready for viewing</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-end mb-5 mb-xl-0">
                        <a class="text-decoration-none" href="#!">
                            
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </section>
        </main>
		


		
	
		
      <?php include 'includes/footer.php'; ?>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>

    </body>
</html>
