<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\PayrollAdjustment;
use App\Models\SalaryBreakdown;
use App\Models\SalaryDetail;
use App\Models\SalaryPeriod;
use Throwable;

class PayrollController extends Controller
{
    private SalaryPeriod $periods;
    private SalaryDetail $details;
    private SalaryBreakdown $breakdowns;
    private PayrollAdjustment $adjustments;

    public function __construct()
    {
        $this->periods = new SalaryPeriod();
        $this->details = new SalaryDetail();
        $this->breakdowns = new SalaryBreakdown();
        $this->adjustments = new PayrollAdjustment();
    }

    public function periodIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $year = $request->query('year') !== null ? (int) $request->query('year') : null;
        $status = $request->query('status');

        $result = $this->periods->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $year,
            is_string($status) ? $status : null
        );

        return $this->ok(
            $result['items'],
            'Salary period list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function periodShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->periods->findDetail($id);
        if ($item === null) {
            throw new HttpException('Salary period not found', 404, 'not_found');
        }
        return $this->ok($item, 'Salary period detail');
    }

    public function periodStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'period_code' => ['required', 'string'],
            'period_name' => ['required', 'string'],
            'period_type' => ['required', 'string'],
            'year' => ['required', 'integer'],
            'month' => ['integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'payment_date' => ['date'],
            'standard_working_days' => ['integer'],
            'status' => ['string'],
            'notes' => ['string'],
        ]);
        $id = $this->periods->create($payload);
        return $this->created($this->periods->findDetail($id), 'Salary period created');
    }

    public function periodUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->periods->find($id) === null) {
            throw new HttpException('Salary period not found', 404, 'not_found');
        }
        $payload = Validator::validate($request->all(), [
            'period_name' => ['string'],
            'period_type' => ['string'],
            'year' => ['integer'],
            'month' => ['integer'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'payment_date' => ['date'],
            'standard_working_days' => ['integer'],
            'status' => ['string'],
            'notes' => ['string'],
            'closed_by' => ['integer'],
            'closed_date' => ['date'],
        ]);
        $this->periods->updateById($id, $payload);
        return $this->ok($this->periods->findDetail($id), 'Salary period updated');
    }

    public function periodClose(Request $request, array $params): array
    {
        $periodId = (int) ($params['id'] ?? 0);
        $period = $this->periods->findDetail($periodId);
        if ($period === null) {
            throw new HttpException('Salary period not found', 404, 'not_found');
        }

        $status = strtoupper((string) ($period['status'] ?? ''));
        if (in_array($status, ['CLOSED', 'PAID'], true)) {
            throw new HttpException('Salary period already finalized', 422, 'validation_error');
        }

        $monthKey = $this->resolveApplyMonthByPeriod($period);
        $details = $this->details->listByPeriodId($periodId);

        $db = Database::connection();
        $updatedEmployees = 0;
        $updatedAdjustments = 0;
        $updatedAmount = 0.0;
        $authUser = $request->attribute('auth_user');

        try {
            $db->beginTransaction();

            foreach ($details as $detail) {
                $salaryDetailId = (int) ($detail['salary_detail_id'] ?? 0);
                $employeeId = (int) ($detail['employee_id'] ?? 0);
                if ($salaryDetailId <= 0 || $employeeId <= 0) {
                    continue;
                }

                $pendingAdjustments = $this->adjustments->listPendingByEmployeeMonth($employeeId, $monthKey);
                if ($pendingAdjustments === []) {
                    continue;
                }

                $adjustmentIds = [];
                $sum = 0.0;
                foreach ($pendingAdjustments as $adjustment) {
                    $adjustmentIds[] = (int) $adjustment['adjustment_id'];
                    $sum += (float) ($adjustment['amount'] ?? 0);
                }

                if ($sum == 0.0) {
                    $updatedAdjustments += $this->adjustments->markPaidByIds($adjustmentIds, $salaryDetailId, $periodId);
                    continue;
                }

                $currentGross = (float) ($detail['gross_salary'] ?? 0);
                $currentNet = (float) ($detail['net_salary'] ?? 0);
                $currentAllowances = (float) ($detail['total_allowances'] ?? 0);
                $currentDeductions = (float) ($detail['total_deductions'] ?? 0);

                $allowanceDelta = $sum > 0 ? $sum : 0.0;
                $deductionDelta = $sum < 0 ? abs($sum) : 0.0;

                $this->details->updateById($salaryDetailId, [
                    'gross_salary' => $currentGross + $allowanceDelta,
                    'net_salary' => $currentNet + $sum,
                    'total_allowances' => $currentAllowances + $allowanceDelta,
                    'total_deductions' => $currentDeductions + $deductionDelta,
                ]);

                $updatedAdjustments += $this->adjustments->markPaidByIds($adjustmentIds, $salaryDetailId, $periodId);
                $updatedEmployees++;
                $updatedAmount += $sum;
            }

            $this->periods->updateById($periodId, [
                'status' => 'CLOSED',
                'closed_by' => (int) ($authUser['employee_id'] ?? 0),
                'closed_date' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($exception instanceof HttpException) {
                throw $exception;
            }

            throw new HttpException('Failed to close salary period: ' . $exception->getMessage(), 500, 'server_error');
        }

        return $this->ok([
            'period_id' => $periodId,
            'apply_month' => $monthKey,
            'employees_updated' => $updatedEmployees,
            'adjustments_marked_paid' => $updatedAdjustments,
            'adjustment_amount_total' => round($updatedAmount, 2),
            'period_status' => 'CLOSED',
        ], 'Salary period closed successfully');
    }

    public function detailIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeIds = $request->attribute('scope_employee_ids');
        $periodId = $request->query('period_id') !== null ? (int) $request->query('period_id') : null;
        $transferStatus = $request->query('transfer_status');

        $result = $this->details->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $periodId,
            is_array($scopeIds) ? $scopeIds : null,
            is_string($transferStatus) ? $transferStatus : null
        );
        return $this->ok(
            $result['items'],
            'Salary detail list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function detailShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->details->findDetail($id);
        if ($item === null) {
            throw new HttpException('Salary detail not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $item['payroll_adjustments'] = $this->adjustments->listAppliedBySalaryDetail($id);
        return $this->ok($item, 'Salary detail detail');
    }

    public function detailStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'period_id' => ['required', 'integer'],
            'employee_id' => ['integer'],
            'contract_id' => ['integer'],
            'basic_salary' => ['required', 'numeric'],
            'gross_salary' => ['required', 'numeric'],
            'net_salary' => ['required', 'numeric'],
            'total_allowances' => ['numeric'],
            'total_deductions' => ['numeric'],
            'overtime_pay' => ['numeric'],
            'leave_pay' => ['numeric'],
            'bonus' => ['numeric'],
            'penalty' => ['numeric'],
            'personal_income_tax' => ['numeric'],
            'advance_payment' => ['numeric'],
            'bank_account' => ['string'],
            'bank_name' => ['string'],
            'transfer_status' => ['string'],
            'notes' => ['string'],
        ]);

        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($payload['employee_id'] ?? $request->attribute('forced_employee_id') ?? ($authUser['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            throw new HttpException('employee_id is required', 422, 'validation_error');
        }
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, $employeeId, true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload['employee_id'] = $employeeId;
        $id = $this->details->create($payload);
        return $this->created($this->details->findDetail($id), 'Salary detail created');
    }

    public function detailUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->details->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Salary detail not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'contract_id' => ['integer'],
            'basic_salary' => ['numeric'],
            'gross_salary' => ['numeric'],
            'net_salary' => ['numeric'],
            'total_allowances' => ['numeric'],
            'total_deductions' => ['numeric'],
            'overtime_pay' => ['numeric'],
            'leave_pay' => ['numeric'],
            'bonus' => ['numeric'],
            'penalty' => ['numeric'],
            'personal_income_tax' => ['numeric'],
            'advance_payment' => ['numeric'],
            'bank_account' => ['string'],
            'bank_name' => ['string'],
            'transfer_status' => ['string'],
            'transfer_date' => ['date'],
            'notes' => ['string'],
        ]);
        $this->details->updateById($id, $payload);
        return $this->ok($this->details->findDetail($id), 'Salary detail updated');
    }

    public function breakdownIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $salaryDetailId = $request->query('salary_detail_id') !== null ? (int) $request->query('salary_detail_id') : null;
        $itemType = $request->query('item_type');

        $result = $this->breakdowns->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $salaryDetailId,
            is_string($itemType) ? $itemType : null
        );
        return $this->ok(
            $result['items'],
            'Salary breakdown list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function breakdownShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->breakdowns->findDetail($id);
        if ($item === null) {
            throw new HttpException('Salary breakdown not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        return $this->ok($item, 'Salary breakdown detail');
    }

    public function breakdownStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'salary_detail_id' => ['required', 'integer'],
            'item_type' => ['required', 'string'],
            'item_id' => ['integer'],
            'item_name' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'is_taxable' => ['boolean'],
            'is_insurable' => ['boolean'],
            'description' => ['string'],
        ]);
        $id = $this->breakdowns->create($payload);
        return $this->created($this->breakdowns->findDetail($id), 'Salary breakdown created');
    }

    public function breakdownUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->breakdowns->find($id) === null) {
            throw new HttpException('Salary breakdown not found', 404, 'not_found');
        }
        $payload = Validator::validate($request->all(), [
            'item_type' => ['string'],
            'item_id' => ['integer'],
            'item_name' => ['string'],
            'amount' => ['numeric'],
            'is_taxable' => ['boolean'],
            'is_insurable' => ['boolean'],
            'description' => ['string'],
        ]);
        $this->breakdowns->updateById($id, $payload);
        return $this->ok($this->breakdowns->findDetail($id), 'Salary breakdown updated');
    }

    public function breakdownDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($this->breakdowns->find($id) === null) {
            throw new HttpException('Salary breakdown not found', 404, 'not_found');
        }
        $this->breakdowns->deleteById($id);
        return $this->ok(null, 'Salary breakdown deleted');
    }

    public function adjustmentIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeIds = $request->attribute('scope_employee_ids');
        $employeeId = $request->query('employee_id') !== null ? (int) $request->query('employee_id') : null;
        $applyMonth = $request->query('apply_month');
        $status = $request->query('status');
        $statusInt = null;
        if ($status !== null && $status !== '') {
            if (!in_array((string) $status, ['0', '1'], true)) {
                throw new HttpException('status must be 0 or 1', 422, 'validation_error');
            }
            $statusInt = (int) $status;
        }

        if ($applyMonth !== null && $applyMonth !== '') {
            $this->assertApplyMonth((string) $applyMonth);
        }

        $result = $this->adjustments->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $employeeId,
            is_string($applyMonth) ? $applyMonth : null,
            $statusInt,
            is_array($scopeIds) ? $scopeIds : null
        );

        return $this->ok(
            $result['items'],
            'Payroll adjustment list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function adjustmentShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->adjustments->findDetail($id);
        if ($item === null) {
            throw new HttpException('Payroll adjustment not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        return $this->ok($item, 'Payroll adjustment detail');
    }

    public function adjustmentStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'employee_id' => ['integer'],
            'amount' => ['required', 'numeric'],
            'description' => ['required', 'string'],
            'apply_month' => ['required', 'string'],
        ]);

        $this->assertApplyMonth((string) ($payload['apply_month'] ?? ''));

        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($payload['employee_id'] ?? $request->attribute('forced_employee_id') ?? ($authUser['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            throw new HttpException('employee_id is required', 422, 'validation_error');
        }
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, $employeeId, true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload['employee_id'] = $employeeId;
        $payload['status'] = 0;
        $payload['paid_salary_detail_id'] = null;
        $payload['paid_period_id'] = null;
        $payload['paid_at'] = null;

        $id = $this->adjustments->create($payload);
        return $this->created($this->adjustments->findDetail($id), 'Payroll adjustment created');
    }

    public function adjustmentUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->adjustments->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Payroll adjustment not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        if ((int) ($existing['status'] ?? 0) === 1) {
            throw new HttpException('Cannot update an adjustment that has already been paid', 422, 'validation_error');
        }

        $payload = Validator::validate($request->all(), [
            'amount' => ['numeric'],
            'description' => ['string'],
            'apply_month' => ['string'],
        ]);

        if (isset($payload['apply_month'])) {
            $this->assertApplyMonth((string) $payload['apply_month']);
        }

        $this->adjustments->updateById($id, $payload);
        return $this->ok($this->adjustments->findDetail($id), 'Payroll adjustment updated');
    }

    private function assertApplyMonth(string $value): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            throw new HttpException('apply_month must be in YYYY-MM format', 422, 'validation_error');
        }
    }

    private function resolveApplyMonthByPeriod(array $period): string
    {
        $year = isset($period['year']) ? (int) $period['year'] : 0;
        $month = isset($period['month']) ? (int) $period['month'] : 0;
        if ($year > 0 && $month >= 1 && $month <= 12) {
            return sprintf('%04d-%02d', $year, $month);
        }

        $startDate = (string) ($period['start_date'] ?? '');
        if ($startDate === '' || strtotime($startDate) === false) {
            throw new HttpException('Salary period start_date is invalid', 422, 'validation_error');
        }

        return date('Y-m', strtotime($startDate));
    }
}
