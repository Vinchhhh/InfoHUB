const container = document.getElementById("container")
const signup = document.getElementById("signUp")
const signin = document.getElementById("signIn")

signup.addEventListener("click", function(){
    container.classList.add("right-panel-active");
})

signin.addEventListener("click", function(){
    container.classList.remove("right-panel-active");
})