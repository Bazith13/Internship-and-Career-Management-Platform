// MEMBER 1: Show the organization field only for employer registration.
const accountRole = document.querySelector('#account-role');
const organizationField = document.querySelector('#organization-field');

if (accountRole && organizationField) {
    const updateOrganizationField = () => {
        const isEmployer = accountRole.value === 'employer';
        organizationField.classList.toggle('hidden', !isEmployer);
        organizationField.querySelector('input').required = isEmployer;
    };

    accountRole.addEventListener('change', updateOrganizationField);
    updateOrganizationField();
}
