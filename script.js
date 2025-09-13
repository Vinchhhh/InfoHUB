const container = document.getElementById("container")
const signup = document.getElementById("signUp")
const signin = document.getElementById("signIn")
const signupMobile = document.getElementById("signUpMobile")
const signinMobile = document.getElementById("signInMobile")

if (signup) {
    signup.addEventListener("click", function () {
        container.classList.add("right-panel-active");
    })
}

signupMobile.addEventListener("click", function () {
    container.classList.add("right-panel-active");
})

if (signin) {
    signin.addEventListener("click", function () {
        container.classList.remove("right-panel-active");
    })
}

signinMobile.addEventListener("click", function () {
    container.classList.remove("right-panel-active");
})