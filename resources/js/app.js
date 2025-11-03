import Alpine from "alpinejs"
// import focus from "@alpinejs/focus"
import "./bootstrap"

// Alpine.plugin(focus)

window.Alpine = Alpine
Alpine.start()

// prevent accidentally submitting a form more than once
window.addEventListener("DOMContentLoaded", () => {
	document.querySelectorAll("form:not(.no-spinner)").forEach((form) => {
		form.addEventListener("submit", (event) => {
			event.target.querySelectorAll("textarea,input").forEach((input) => {
				input.setAttribute("readonly", true)
			})
			event.target.querySelectorAll("button").forEach((input) => {
				input.disabled = true
			})

			let clone = document.createElement("input")
			clone.setAttribute("type", "hidden")
			clone.setAttribute("name", event.submitter.attributes.getNamedItem("name")?.value)
			clone.setAttribute(
				"value",
				event.submitter.attributes.getNamedItem("value")?.value ?? event.submitter.innerText
			)

			event.submitter.innerText += " "
			event.submitter.appendChild(document.getElementById("spinner"))
			event.target.appendChild(clone)
		})
	})
})
