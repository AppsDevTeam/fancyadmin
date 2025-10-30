const run = (el) => {
    $(function () {
        $(document).on('click', '[data-sign-in-as-identity-url]', function () {
            const buttonText  = $(this).attr('data-button-text');

            $.nette.ajax({
                url: $(this).attr('data-sign-in-as-identity-url'),
            }).done(function (payload) {
                // Modal s tlačítkem
                const modal = document.createElement('div');
                modal.style.position = 'fixed';
                modal.style.top = '50%';
                modal.style.left = '50%';
                modal.style.transform = 'translate(-50%, -50%)';
                modal.style.padding = '20px';
                modal.style.minWidth = '300px';
                modal.style.backgroundColor = '#fff';
                modal.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
                modal.style.zIndex = '1000';
                modal.style.textAlign = 'center';
                modal.style.display = 'flex';
                modal.style.justifyContent = 'center';
                modal.style.alignItems = 'center';

                // Tlačítko pro zavření (vpravo nahoře)
                const closeButton = document.createElement('button');
                closeButton.textContent = '×';
                closeButton.style.position = 'absolute';
                closeButton.style.top = '10px';
                closeButton.style.right = '10px';
                closeButton.style.background = 'none';
                closeButton.style.border = 'none';
                closeButton.style.fontSize = '26px';
                closeButton.style.cursor = 'pointer';
                closeButton.onclick = () => modal.remove();
                modal.appendChild(closeButton);

                // Tlačítko s odkazem
                const linkButton = document.createElement('a');
                linkButton.href = payload.signAsIdentityLink;
                linkButton.textContent = buttonText;
                linkButton.className = 'btn btn-primary my-5';
                modal.appendChild(linkButton);

                document.body.appendChild(modal);
            });
        });
    });
};

export default { run };