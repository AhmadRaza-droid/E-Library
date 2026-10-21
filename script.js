function searchBooks() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let books = document.getElementsByClassName("search-card");

    for (let i = 0; i < books.length; i++) {
        let bookText = books[i].innerText.toLowerCase();

        if (bookText.includes(input)) {
            books[i].style.display = "block";
        } else {
            books[i].style.display = "none";
        }
    }
}
function sendMessage(){
    alert("Your message has been sent successfully!");
}