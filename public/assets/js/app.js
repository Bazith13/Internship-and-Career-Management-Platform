document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.nav-links');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }
  const role = document.querySelector('#account-role');
  const organization = document.querySelector('#organization-field');
  if (role && organization) {
    const sync = () => {
      const employer = role.value === 'employer';
      organization.hidden = !employer;
      organization.querySelector('input').required = employer;
    };
    role.addEventListener('change', sync);
    sync();
  }
});
