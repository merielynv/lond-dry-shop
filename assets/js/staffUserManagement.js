document.addEventListener("DOMContentLoaded", function () {

    // ================================
    // SEARCH USERS
    // ================================

    const staffSearch = document.getElementById("staffSearch");
    const staffRows = document.querySelectorAll(".staff-row");

    if (staffSearch) {
        staffSearch.addEventListener("input", function () {

            const searchValue = staffSearch.value.toLowerCase();

            staffRows.forEach(function (row) {

                const searchText = row.getAttribute("data-search") || "";

                if (searchText.toLowerCase().indexOf(searchValue) !== -1) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }

            });
        });
    }


    // ================================
    // ADD / EDIT USER MODAL
    // ================================

    const staffModal = document.getElementById("staffModal");
    const staffForm = document.getElementById("staffForm");

    const staffFormAction = document.getElementById("staffFormAction");
    const staffUserId = document.getElementById("staffUserId");
    const staffFullName = document.getElementById("staffFullName");
    const staffUsername = document.getElementById("staffUsername");
    const staffRole = document.getElementById("staffRole");
    const staffPassword = document.getElementById("staffPassword");
    const passwordHint = document.getElementById("passwordHint");

    const staffModalTitle = document.getElementById("staffModalTitle");
    const staffSubmit = document.getElementById("staffSubmit");


    // ADD USER BUTTON
    const addStaffButton = document.getElementById("openStaffModal");

    if (addStaffButton) {
        addStaffButton.addEventListener("click", function () {

            staffFormAction.value = "add";
            staffUserId.value = "";

            staffFullName.value = "";
            staffUsername.value = "";
            staffRole.value = "staff";
            staffPassword.value = "";

            staffModalTitle.textContent = "Add User";
            staffSubmit.textContent = "Add User";

            passwordHint.textContent = "Password must be at least 8 characters.";

            staffModal.classList.add("is-open");
        });
    }


    // EDIT USER BUTTONS
    const editButtons = document.querySelectorAll(".staff-edit");

    editButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            staffFormAction.value = "edit";

            staffUserId.value = button.getAttribute("data-id");

            staffFullName.value = button.getAttribute("data-name");
            staffUsername.value = button.getAttribute("data-username");
            staffRole.value = button.getAttribute("data-role");

            staffPassword.value = "";

            staffModalTitle.textContent = "Edit User";
            staffSubmit.textContent = "Save Changes";

            passwordHint.textContent =
                "Leave password blank if you do not want to change it.";

            staffModal.classList.add("is-open");
        });

    });


    // ================================
    // CLOSE ADD / EDIT MODAL
    // ================================

    const closeStaffModal = document.getElementById("closeStaffModal");
    const cancelStaffButton = document.getElementById("cancelStaffModal");

    if (closeStaffModal) {
        closeStaffModal.addEventListener("click", function () {
            staffModal.classList.remove("is-open");
        });
    }

    if (cancelStaffButton) {
        cancelStaffButton.addEventListener("click", function () {
            staffModal.classList.remove("is-open");
        });
    }


    // CLICK OUTSIDE MODAL
    if (staffModal) {
        staffModal.addEventListener("click", function (event) {

            if (event.target === staffModal) {
                staffModal.classList.remove("is-open");
            }

        });
    }


    // ================================
    // ADD / EDIT FORM VALIDATION
    // ================================

    if (staffForm) {

        staffForm.addEventListener("submit", function (event) {

            const action = staffFormAction.value;
            const password = staffPassword.value.trim();

            // Password is required when adding
            if (action === "add" && password.length < 8) {

                event.preventDefault();

                alert("Password must be at least 8 characters.");

                staffPassword.focus();

                return;
            }

            // Password is optional when editing
            if (action === "edit" && password !== "" && password.length < 8) {

                event.preventDefault();

                alert("New password must be at least 8 characters.");

                staffPassword.focus();

                return;
            }

            // Prevent double submission
            if (staffSubmit) {
                staffSubmit.disabled = true;

                if (action === "add") {
                    staffSubmit.textContent = "Adding...";
                } else {
                    staffSubmit.textContent = "Saving...";
                }
            }

        });

    }


    // ================================
    // ENABLE / DISABLE USER MODAL
    // ================================

    const statusModal = document.getElementById("statusModal");
    const statusForm = document.getElementById("statusForm");

    const statusAction = document.getElementById("statusAction");
    const statusUserId = document.getElementById("statusUserId");

    const statusModalTitle = document.getElementById("statusModalTitle");
    const statusModalText = document.getElementById("statusModalText");
    const statusSubmit = document.getElementById("statusSubmit");

    const statusButtons = document.querySelectorAll(
        ".staff-enable, .staff-disable"
    );


    statusButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            const userId = button.getAttribute("data-id");
            const userName = button.getAttribute("data-name");
            const action = button.getAttribute("data-action");

            statusUserId.value = userId;
            statusAction.value = action;

            if (action === "disable") {

                statusModalTitle.textContent = "Disable User";

                statusModalText.textContent =
                    "Are you sure you want to disable " +
                    userName +
                    "?";

                statusSubmit.textContent = "Disable User";

            } else {

                statusModalTitle.textContent = "Enable User";

                statusModalText.textContent =
                    "Are you sure you want to enable " +
                    userName +
                    "?";

                statusSubmit.textContent = "Enable User";
            }

            statusModal.classList.add("is-open");

        });

    });


    // CLOSE STATUS MODAL

    const closeStatusModal = document.getElementById("closeStatusModal");
    const cancelStatusButton = document.getElementById("cancelStatusButton");

    if (closeStatusModal) {
        closeStatusModal.addEventListener("click", function () {
            statusModal.classList.remove("is-open");
        });
    }

    if (cancelStatusButton) {
        cancelStatusButton.addEventListener("click", function () {
            statusModal.classList.remove("is-open");
        });
    }


    // CLICK OUTSIDE STATUS MODAL

    if (statusModal) {

        statusModal.addEventListener("click", function (event) {

            if (event.target === statusModal) {
                statusModal.classList.remove("is-open");
            }

        });

    }


    // STATUS FORM SUBMIT

    if (statusForm) {

        statusForm.addEventListener("submit", function () {

            if (statusSubmit) {

                statusSubmit.disabled = true;

                if (statusAction.value === "disable") {
                    statusSubmit.textContent = "Disabling...";
                } else {
                    statusSubmit.textContent = "Enabling...";
                }

            }

        });

    }


    // ================================
    // DELETE USER MODAL
    // ================================

    const deleteModal = document.getElementById("deleteModal");
    const deleteForm = document.getElementById("deleteForm");

    const deleteUserId = document.getElementById("deleteUserId");
    const deleteModalText = document.getElementById("deleteModalText");
    const confirmDelete = document.getElementById("confirmDelete");

    const deleteButtons = document.querySelectorAll(".staff-delete");


    deleteButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            const userId = button.getAttribute("data-id");
            const userName = button.getAttribute("data-name");

            deleteUserId.value = userId;

            deleteModalText.textContent =
                "Are you sure you want to delete " +
                userName +
                "? This action cannot be undone.";

            deleteModal.classList.add("is-open");

        });

    });


    // CLOSE DELETE MODAL

    const closeDeleteModal = document.getElementById("closeDeleteModal");
    const cancelDeleteButton = document.getElementById("cancelDeleteButton");

    if (closeDeleteModal) {

        closeDeleteModal.addEventListener("click", function () {
            deleteModal.classList.remove("is-open");
        });

    }

    if (cancelDeleteButton) {

        cancelDeleteButton.addEventListener("click", function () {
            deleteModal.classList.remove("is-open");
        });

    }


    // CLICK OUTSIDE DELETE MODAL

    if (deleteModal) {

        deleteModal.addEventListener("click", function (event) {

            if (event.target === deleteModal) {
                deleteModal.classList.remove("is-open");
            }

        });

    }


    // DELETE FORM SUBMIT

    if (deleteForm) {

        deleteForm.addEventListener("submit", function () {

            if (confirmDelete) {

                confirmDelete.disabled = true;
                confirmDelete.textContent = "Deleting...";

            }

        });

    }


    // ================================
    // ESC KEY CLOSES MODALS
    // ================================

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            if (staffModal) {
                staffModal.classList.remove("is-open");
            }

            if (statusModal) {
                statusModal.classList.remove("is-open");
            }

            if (deleteModal) {
                deleteModal.classList.remove("is-open");
            }

        }

    });

}); 