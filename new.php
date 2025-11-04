<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nigerian PAYE Salary Calculator (Annual Input)</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f4f7f6; }
        .container { max-width: 650px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="number"] { width: 100%; padding: 12px; margin-bottom: 25px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        button { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; transition: background-color 0.3s; }
        button:hover { background-color: #218838; }
        #results { margin-top: 30px; border-top: 2px dashed #ccc; padding-top: 20px; }
        #results p { margin: 5px 0; display: flex; justify-content: space-between; padding-right: 10px; }
        .section-heading { font-weight: bold; color: #343a40; margin-top: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .key-figure { font-size: 1.1em; font-weight: bold; color: #dc3545; }
        .net-pay { font-size: 1.6em; font-weight: bold; color: #007bff; margin-top: 20px; padding: 15px; background-color: #e9f5ff; border-radius: 4px; text-align: center; }
        .disclaimer { margin-top: 25px; font-size: 0.85em; color: #6c757d; border-top: 1px dashed #ccc; padding-top: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h2>🇳🇬 Nigerian Annual Salary Calculator</h2>

    <form onsubmit="event.preventDefault(); displayCalculation();">
        <label for="annualGrossSalary">Enter **Annual** Gross Salary (₦)</label>
        <input type="number" id="annualGrossSalary" value="0.00" step="0.01" required>
        
        <button type="submit">Calculate Annual & Monthly Pay</button>
    </form>

    <div id="results">
        </div>
    
    <div class="disclaimer">
        * Calculation uses the provided salary percentage breakdown. The **National Housing Fund (NHF)** deduction is excluded to match the user's specific tax and take-home figures.
    </div>
</div>

<script>
    function formatNaira(amount) {
        // Rounds to two decimal places and adds comma separation
        return `₦${(Math.round(amount * 100) / 100).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`;
    }

    function calculateNigerianPAYE(annualGrossSalary) {
        const MONTHS_IN_YEAR = 12;
        const monthlyGrossSalary = annualGrossSalary / MONTHS_IN_YEAR;

        // 1. Component Breakdown Percentages (fixed from user input)
        const BASIC_PERCENT = 0.6665;
        const HOUSING_PERCENT = 0.1875;
        const TRANSPORT_PERCENT = 0.0800;
        const UTILITY_PERCENT = 0.0375;
        const MEAL_PERCENT = 0.0285;
        
        // 2. Monthly Component Breakdown
        const basicMonthly = monthlyGrossSalary * BASIC_PERCENT;
        const housingMonthly = monthlyGrossSalary * HOUSING_PERCENT;
        const transportMonthly = monthlyGrossSalary * TRANSPORT_PERCENT;
        const utilityMonthly = monthlyGrossSalary * UTILITY_PERCENT;
        const mealMonthly = monthlyGrossSalary * MEAL_PERCENT;

        // 3. Statutory Deductions Calculation
        // Pension is 8% of (Basic + Housing + Transport) - BHT
        const pensionBasisMonthly = basicMonthly + housingMonthly + transportMonthly;
        const pensionEmployeeMonthly = pensionBasisMonthly * 0.08;
        const pensionAnnual = pensionEmployeeMonthly * MONTHS_IN_YEAR;
        
        // NHF is 2.5% of Gross Income. (Set to 0.00 to match user's reconciliation)
        const nhfEmployeeMonthly = 0.00; 
        const nhfAnnual = 0.00;

        // 4. Calculating Consolidated Relief Allowance (CRA)
        // CRA = Higher of (N200,000 or 1% of Gross) + 20% of Gross
        const onePercentGross = annualGrossSalary * 0.01;
        
        const craFixed = Math.max(200000, onePercentGross);
        const craPercentage = annualGrossSalary * 0.20;
        
        const consolidatedReliefAllowance = craFixed + craPercentage;
        
        // 5. Calculating Taxable Income (Annual)
        // Taxable Income = Annual Gross - Allowable Deductions (Pension/NHF) - CRA
        let taxableIncomeAnnual = (
            annualGrossSalary - pensionAnnual - nhfAnnual - consolidatedReliefAllowance
        );
        taxableIncomeAnnual = Math.max(0, taxableIncomeAnnual);

        // 6. Applying PAYE Tax Rates (Annual)
        let payeAnnual = 0;
        let remainingTaxable = taxableIncomeAnnual;

        // Tax Brackets and Rates (Based on PITA 2011/Finance Acts)
        const brackets = [
            { limit: 300000, rate: 0.07 },
            { limit: 300000, rate: 0.11 },
            { limit: 500000, rate: 0.15 },
            { limit: 500000, rate: 0.19 },
            { limit: 1600000, rate: 0.21 },
            { limit: Infinity, rate: 0.24 }
        ];

        for (const bracket of brackets) {
            if (remainingTaxable <= 0) break;
            
            const chargeable = Math.min(remainingTaxable, bracket.limit);
            payeAnnual += chargeable * bracket.rate;
            remainingTaxable -= chargeable;
        }
        
        // 7. Final PAYE Tax (Monthly)
        const payeMonthly = payeAnnual / MONTHS_IN_YEAR;

        // 8. Net Pay Calculation (Monthly & Annual)
        const totalDeductionsMonthly = pensionEmployeeMonthly + nhfEmployeeMonthly + payeMonthly;
        const totalDeductionsAnnual = totalDeductionsMonthly * MONTHS_IN_YEAR;
        
        const netPayMonthly = monthlyGrossSalary - totalDeductionsMonthly;
        const netPayAnnual = annualGrossSalary - totalDeductionsAnnual;
        
        return {
            annual: {
                gross: annualGrossSalary,
                pension: pensionAnnual,
                nhf: nhfAnnual,
                tax: payeAnnual,
                totalDeductions: totalDeductionsAnnual,
                netPay: netPayAnnual
            },
            monthly: {
                gross: monthlyGrossSalary,
                basic: basicMonthly,
                housing: housingMonthly,
                transport: transportMonthly,
                utility: utilityMonthly,
                meal: mealMonthly,
                pension: pensionEmployeeMonthly,
                nhf: nhfEmployeeMonthly,
                tax: payeMonthly,
                totalDeductions: totalDeductionsMonthly,
                netPay: netPayMonthly
            },
            taxDetails: {
                cra: consolidatedReliefAllowance,
                taxableIncome: taxableIncomeAnnual
            }
        };
    }

    function displayCalculation() {
        const salaryInput = document.getElementById('annualGrossSalary');
        const annualGross = parseFloat(salaryInput.value);

        if (isNaN(annualGross) || annualGross <= 0) {
            document.getElementById('results').innerHTML = '<p style="color: red;">Please enter a valid annual salary.</p>';
            return;
        }

        const result = calculateNigerianPAYE(annualGross);
        const resultsDiv = document.getElementById('results');

        resultsDiv.innerHTML = `
            <h3>✅ Payroll Calculation Summary</h3>
            
            <div class="section-heading">Monthly Breakdown</div>
            <p><strong>Gross Monthly Salary:</strong> <span>${formatNaira(result.monthly.gross)}</span></p>
            <p style="margin-left: 20px;">Basic (${(result.monthly.basic/result.monthly.gross*100).toFixed(2)}%): <span>${formatNaira(result.monthly.basic)}</span></p>
            <p style="margin-left: 20px;">Housing (${(result.monthly.housing/result.monthly.gross*100).toFixed(2)}%): <span>${formatNaira(result.monthly.housing)}</span></p>
            <p style="margin-left: 20px;">Transport (${(result.monthly.transport/result.monthly.gross*100).toFixed(2)}%): <span>${formatNaira(result.monthly.transport)}</span></p>
            <p style="margin-left: 20px;">Utility (${(result.monthly.utility/result.monthly.gross*100).toFixed(2)}%): <span>${formatNaira(result.monthly.utility)}</span></p>
            <p style="margin-left: 20px;">Meal (${(result.monthly.meal/result.monthly.gross*100).toFixed(2)}%): <span>${formatNaira(result.monthly.meal)}</span></p>

            <div class="section-heading">Monthly Deductions</div>
            <p><strong>Employee Pension:</strong> <span><span class="key-figure">${formatNaira(result.monthly.pension)}</span></span></p>
            <p><strong>PAYE Income Tax:</strong> <span><span class="key-figure">${formatNaira(result.monthly.tax)}</span></span></p>
            <p style="margin-left: 20px; font-size: 0.9em; color: #6c757d;">*(NHF deduction is N0.00 as per reconciled figures)*</p>

            <div class="section-heading">Annual & Total Summary</div>
            <p><strong>Annual Gross Salary:</strong> <span>${formatNaira(result.annual.gross)}</span></p>
            <p><strong>Annual Pension Total:</strong> <span><span class="key-figure">${formatNaira(result.annual.pension)}</span></span></p>
            <p><strong>Annual Tax Total:</strong> <span><span class="key-figure">${formatNaira(result.annual.tax)}</span></span></p>
            
            <div class="net-pay">
                Monthly Take-Home Pay (Net): ${formatNaira(result.monthly.netPay)}
            </div>
             <p style="text-align: center; font-weight: bold;">Annual Take-Home Pay (Net): ${formatNaira(result.annual.netPay)}</p>

            <h4 style="margin-top: 25px;">Tax Calculation Basis:</h4>
            <p style="margin-left: 20px;">Consolidated Relief Allowance (CRA): <span>${formatNaira(result.taxDetails.cra)}</span></p>
            <p style="margin-left: 20px;">Annual Taxable Income: <span>${formatNaira(result.taxDetails.taxableIncome)}</span></p>
        `;
    }

    // Initialize with default annual salary (70000 * 12 = 840000)
    document.addEventListener('DOMContentLoaded', () => {
        displayCalculation();
    });
</script>

</body>
</html>