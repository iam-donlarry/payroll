<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Salary Breakdown Calculator</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      max-width: 800px;
      margin: 30px auto;
      padding: 20px;
      background-color: #f9f9f9;
    }
    h1 {
      text-align: center;
      color: #2c3e50;
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }
    input[type="number"] {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      margin-top: 5px;
      box-sizing: border-box;
    }
    button {
      margin-top: 15px;
      padding: 10px 20px;
      font-size: 16px;
      background-color: #3498db;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover {
      background-color: #2980b9;
    }
    .result {
      margin-top: 25px;
      padding: 15px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .section {
      margin-bottom: 20px;
    }
    .section h3 {
      margin-bottom: 10px;
      color: #2c3e50;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      text-align: left;
      padding: 8px;
      border-bottom: 1px solid #ddd;
    }
    th {
      background-color: #f2f2f2;
    }
  </style>
</head>
<body>

<h1>Annual Salary Breakdown Calculator</h1>

<label for="grossInput">Enter Monthly Gross Salary (e.g., 25000):</label>
<input type="number" id="grossInput" placeholder="Type a number..." min="0" step="100" />
<button onclick="calculateBreakdown()">Calculate</button>

<div id="result" class="result" style="display: none;"></div>

<script>
  function calculateBreakdown() {
    const input = document.getElementById('grossInput');
    const resultDiv = document.getElementById('result');
    const gross = parseFloat(input.value);

    if (isNaN(gross) || gross <= 0) {
      alert('Please enter a valid positive number for gross salary.');
      return;
    }

    // Allowance percentages from payroll.xlsx (based on first 3 staff)
    const allowances = {
      basicSalary: gross * 0.6665,
      housingAllowance: gross * 0.1875,
      transportAllowance: gross * 0.08,
      utilityAllowance: gross * 0.0375,
      meals: gross * 0.0285,
    };

    // Deductions (approximated from sample)
    const deductions = {
      pensionFund: gross * 0.075,
      paye: gross * 0.01,
      staffLoan: 0,
      absentism: 0,
      others: 0,
    };

    const totalDeductions = Object.values(deductions).reduce((a, b) => a + b, 0);
    const netMonthly = gross - totalDeductions;

    // Annual values
    const annualGross = gross * 12;
    const annualNet = netMonthly * 12;
    const annualAllowances = Object.fromEntries(
      Object.entries(allowances).map(([k, v]) => [k, v * 12])
    );
    const annualDeductions = Object.fromEntries(
      Object.entries(deductions).map(([k, v]) => [k, v * 12])
    );

    // Formatting helper
    const f = (n) => n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Build HTML output
    let html = `
      <div class="section">
        <h3>Monthly Breakdown</h3>
        <table>
          <tr><th>Component</th><th>Amount (₦)</th></tr>
          <tr><td>Gross Pay</td><td>${f(gross)}</td></tr>
          <tr><td>Basic Salary</td><td>${f(allowances.basicSalary)}</td></tr>
          <tr><td>Housing Allowance</td><td>${f(allowances.housingAllowance)}</td></tr>
          <tr><td>Transport Allowance</td><td>${f(allowances.transportAllowance)}</td></tr>
          <tr><td>Utility Allowance</td><td>${f(allowances.utilityAllowance)}</td></tr>
          <tr><td>Meals</td><td>${f(allowances.meals)}</td></tr>
          <tr><td><strong>Total Allowances</strong></td><td><strong>${f(gross)}</strong></td></tr>
        </table>
      </div>

      <div class="section">
        <h3>Monthly Deductions</h3>
        <table>
          <tr><th>Deduction</th><th>Amount (₦)</th></tr>
          <tr><td>Pension Fund (7.5%)</td><td>${f(deductions.pensionFund)}</td></tr>
          <tr><td>PAYE (1%)</td><td>${f(deductions.paye)}</td></tr>
          <tr><td>Staff Loan</td><td>${f(deductions.staffLoan)}</td></tr>
          <tr><td>Absentism</td><td>${f(deductions.absentism)}</td></tr>
          <tr><td>Others</td><td>${f(deductions.others)}</td></tr>
          <tr><td><strong>Total Deductions</strong></td><td><strong>${f(totalDeductions)}</strong></td></tr>
        </table>
      </div>

      <div class="section">
        <h3>Summary</h3>
        <table>
          <tr><td>Monthly Net Salary</td><td><strong>₦${f(netMonthly)}</strong></td></tr>
          <tr><td>Annual Gross Salary</td><td><strong>₦${f(annualGross)}</strong></td></tr>
          <tr><td>Annual Net Salary</td><td><strong>₦${f(annualNet)}</strong></td></tr>
        </table>
      </div>

      <div class="section">
        <h3>Annual Allowances</h3>
        <table>
          <tr><th>Component</th><th>Amount (₦)</th></tr>
          <tr><td>Basic Salary</td><td>${f(annualAllowances.basicSalary)}</td></tr>
          <tr><td>Housing Allowance</td><td>${f(annualAllowances.housingAllowance)}</td></tr>
          <tr><td>Transport Allowance</td><td>${f(annualAllowances.transportAllowance)}</td></tr>
          <tr><td>Utility Allowance</td><td>${f(annualAllowances.utilityAllowance)}</td></tr>
          <tr><td>Meals</td><td>${f(annualAllowances.meals)}</td></tr>
        </table>
      </div>
    `;

    resultDiv.innerHTML = html;
    resultDiv.style.display = 'block';
  }
</script>

</body>
</html>