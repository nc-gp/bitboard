let showError = true;

// Function to capitalize the first letter of a string
const capitalizeFirstLetter = str => `${str.charAt(0).toUpperCase()}${str.slice(1)}`;

// Function to display an error toast message
const showErrorToast = (name, text) => 
{
    if(!showError)
        return;

    Toastify({
        text: name + text,
        duration: 5000,
        close: true,
        gravity: "bottom",
        position: "right",
        stopOnFocus: true,
        style: {
            background: "rgb(255, 53, 53)",
            boxShadow: "0 3px 6px -1px rgba(255, 53, 53,.12),0 10px 36px -4px rgba(255, 53, 53,.3)"
        }
    }).showToast();
}

// Function to validate a single input element
const validateInput = input => 
{
    const name = capitalizeFirstLetter(input.getAttribute('name'));
    const value = input.value.trim();
    
    if (value === '') 
    {
        showErrorToast(name, " can't be empty!");
        return false;
    }

    if (input.hasAttribute('cmin')) 
    {
        const chars = parseInt(input.getAttribute('cmin'));
        if (value.length < chars) 
        {
            showErrorToast(name, ` needs to be at least ${chars} characters!`);
            return false;
        }
    }

    if (input.hasAttribute('reg')) 
    {
        const pattern = new RegExp(input.getAttribute('reg'));
        if (!pattern.test(value)) 
        {
            let text = " is not valid!";
            if (name === "Password") 
            {
                if (!/\d/.test(value)) text = " must contain at least one digit.";
                if (!/[a-z]/.test(value)) text = " must contain at least one lowercase letter.";
                if (!/[A-Z]/.test(value)) text = " must contain at least one uppercase letter.";
            }
            showErrorToast(name, text);
            return false;
        }
    }

    return true;
}

const initStep = () => 
{
    document.body.style.transition = "opacity 1s";
    document.body.style.opacity = 1;

    const form = document.getElementById('bb-form');

    form.addEventListener('submit', (e) => 
    {
        e.preventDefault();
        showError = true;
        
        const all = document.querySelectorAll('input[special]');
        let process = true;

        all.forEach(input => {
            if (!validateInput(input) && process) {
                process = false;
                showError = false;
            }
        });

        if (!process) return;

        document.body.style.opacity = 0;
        setTimeout(() => {
            form.submit();
        }, 1000);
    });
}

window.onload = initStep;