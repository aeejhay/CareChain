<?php 
$pageTitle = 'Register';
require_once 'includes/header.php';

if (isLoggedIn()) redirect('/carechain/dashboard.php');

$selectedRole = $_GET['role'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    
    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    if (!in_array($role, ['worker', 'facility'])) $errors[] = 'Please select a role';
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = 'Email already registered';
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$email, $hashedPassword, $role]);
            $userId = $pdo->lastInsertId();
            
            if ($role === 'worker') {
                $firstName = sanitize($_POST['first_name'] ?? '');
                $lastName = sanitize($_POST['last_name'] ?? '');
                $jobTitle = sanitize($_POST['job_title'] ?? 'hca');
                
                $stmt = $pdo->prepare("INSERT INTO worker_profiles (user_id, first_name, last_name, job_title) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $firstName, $lastName, $jobTitle]);
            } else {
                $facilityName = sanitize($_POST['facility_name'] ?? '');
                $facilityType = sanitize($_POST['facility_type'] ?? 'nursing_home');
                $address = sanitize($_POST['address'] ?? '');
                $city = sanitize($_POST['city'] ?? '');
                $county = sanitize($_POST['county'] ?? '');
                
                $stmt = $pdo->prepare("INSERT INTO facility_profiles (user_id, facility_name, facility_type, address, city, county) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $facilityName, $facilityType, $address, $city, $county]);
            }
            
            $pdo->commit();
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
            
            flash('success', 'Welcome to CareChain! Your account has been created.');
            redirect('/carechain/dashboard.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 540px;">
        <h1>Join CareChain</h1>
        <p class="subtitle">Create your account and start connecting</p>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= $e ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm">
            <!-- Role Selection -->
            <div class="role-selector">
                <div class="role-option <?= $selectedRole === 'worker' ? 'selected' : '' ?>" onclick="selectRole('worker')">
                    <h3>&#x1F9D1;&#x200D;&#x2695;&#xFE0F; Care Worker</h3>
                    <p>Find shifts on your terms</p>
                </div>
                <div class="role-option <?= $selectedRole === 'facility' ? 'selected' : '' ?>" onclick="selectRole('facility')">
                    <h3>&#x1F3E5; Facility</h3>
                    <p>Find qualified staff fast</p>
                </div>
            </div>
            <input type="hidden" name="role" id="roleInput" value="<?= $selectedRole ?>">
            
            <!-- Common Fields -->
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            
            <!-- Worker Fields -->
            <div id="workerFields" style="display: <?= $selectedRole === 'worker' ? 'block' : 'none' ?>;">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= sanitize($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= sanitize($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Job Title</label>
                    <select name="job_title" class="form-control">
                        <option value="hca">Healthcare Assistant (HCA)</option>
                        <option value="nurse">Nurse</option>
                        <option value="carer">Carer</option>
                        <option value="midwife">Midwife</option>
                        <option value="physio">Physiotherapist</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            
            <!-- Facility Fields -->
            <div id="facilityFields" style="display: <?= $selectedRole === 'facility' ? 'block' : 'none' ?>;">
                <div class="form-group">
                    <label>Facility Name</label>
                    <input type="text" name="facility_name" class="form-control" value="<?= sanitize($_POST['facility_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Facility Type</label>
                    <select name="facility_type" class="form-control">
                        <option value="nursing_home">Nursing Home</option>
                        <option value="hospital">Hospital</option>
                        <option value="home_care">Home Care</option>
                        <option value="clinic">Clinic</option>
                        <option value="rehab">Rehabilitation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" value="<?= sanitize($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" value="<?= sanitize($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>County</label>
                        <input type="text" name="county" class="form-control" value="<?= sanitize($_POST['county'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Password -->
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Create Account</button>
        </form>
        
        <div class="auth-footer">
            Already have an account? <a href="/carechain/login.php">Log in</a>
        </div>
    </div>
</div>

<script>
function selectRole(role) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    document.getElementById('workerFields').style.display = role === 'worker' ? 'block' : 'none';
    document.getElementById('facilityFields').style.display = role === 'facility' ? 'block' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
