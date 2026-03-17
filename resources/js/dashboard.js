const spinner = document.getElementById("spinner").cloneNode(true)
const tbody = document.querySelector("main table tbody")
if (!(tbody instanceof Element)) throw new Error("tbody must not be null")

spinner.classList.add("big-spinner")

var getMoreRowsFlying = false
var getMoreRowsAgain = false
var getMoreRowsReset = false

window.resetTable = function () {
	getMoreRowsReset = true
	getMoreRows()
}

async function getMoreRows() {
	if (getMoreRowsFlying) {
		getMoreRowsAgain = true
		return
	}

	if (getMoreRowsReset) {
		getMoreRowsReset = false
		tbody.innerHTML = ""
		getMoreRows()
		return
	}

	getMoreRowsFlying = true
	tbody.querySelectorAll("tr.loading").forEach((tr) => tr.remove())

	const offset = tbody.querySelectorAll("tr:not(.loading)").length
	const orderBy = document.querySelector('select[name="order_by"]')?.value
	const carId = document.querySelector('select[name="car_id"]')?.value
	const userId = document.querySelector('select[name="user_id"]')?.value
	const memo = document.querySelector('input[name="memo"]')?.value
	const deleted = document.querySelector('input[name="deleted"]').checked ? "&deleted=on" : ""
	let response

	if (tbody.querySelector("tr.end") != null) {
		getMoreRowsFlying = false
		return
	}

	tbody.innerHTML += `<tr class="loading"><td colspan="5" class="p-2">${spinner.outerHTML}</td></tr>`

	try {
		response = await fetch(`/t?offset=${offset}&order_by=${orderBy}&car_id=${carId}&user_id=${userId}&memo=${memo}${deleted}`, {
			redirect: "error",
		})
	} catch (err) {
		response = null
	}

	if (getMoreRowsReset) {
		getMoreRowsReset = false
		tbody.innerHTML = ""
		getMoreRowsFlying = false
		getMoreRows()
		return
	}

	if (response == null || !response.ok) {
		tbody.innerHTML += '<tr><td colspan="5"><i>Network error</i></td></tr>' // @todo: translate this line
		tbody.querySelectorAll("tr.loading").forEach((tr) => tr.remove())
		return
	}

	tbody.innerHTML += await response.text()

	getMoreRowsFlying = false
	if (getMoreRowsAgain) {
		getMoreRowsAgain = false
		maybeGetMoreRows()
	}

	tbody.querySelectorAll("tr.loading").forEach((tr) => tr.remove())
}

function maybeGetMoreRows() {
	if (window.scrollY + window.innerHeight + 200 < document.body.scrollHeight) return
	getMoreRows()
}

window.addEventListener("scroll", maybeGetMoreRows)
window.addEventListener("resize", maybeGetMoreRows)

getMoreRows()
