<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace CloudLoyalty\Api\Generated\Model;

// The SDK getters have non-null PHPDoc, but the generated fields are uninitialized
// until the corresponding JSON keys are supplied by the response.
class GetBalanceResponse
{
    /** @return ClientInfoReply|null */
    public function getClient() {}
}
class ClientInfoReply
{
    /** @return int|null */
    public function getBonuses() {}
    /** @return int|null */
    public function getPendingBonuses() {}
}
class GetBonusHistoryResponse
{
    /** @return list<BonusHistoryEntry>|null */
    public function getHistory() {}
    /** @return GetBonusHistoryResponsePagination|null */
    public function getPagination() {}
}
class V2CalculatePurchaseResponse
{
    /** @return CalculationResult|null */
    public function getCalculationResult() {}
}
class CalculationResult
{
    /** @return list<CalculationResultRow>|null */
    public function getRows() {}
    /** @return CalculationResultSummary|null */
    public function getSummary() {}
    /** @return CalculationResultBonuses|null */
    public function getBonuses() {}
    /** @return CalculationResultPromocode|null */
    public function getPromocode() {}
}
class CalculationResultRow
{
    /** @return CalculationResultDiscounts|null */
    public function getDiscounts() {}
}
class CalculationResultBonuses
{
    /** @return Error|null */
    public function getError() {}
}
class BonusHistoryEntry {}
class GetBonusHistoryResponsePagination {}
class CalculationResultSummary {}
class CalculationResultPromocode {}
class CalculationResultDiscounts {}
class Error {}
class V2SetOrderResponse
{
    /** @return CalculationResult|null */
    public function getCalculationResult() {}
}
class ConfirmOrderResponse
{
    /** @return OperationResult|null */
    public function getOperationResult() {}
}
class CancelOrderResponse
{
    /** @return OperationResult|null */
    public function getOperationResult() {}
}
class ApplyReturnResponse
{
    /** @return ApplyReturnResponseConfirmation|null */
    public function getConfirmation() {}
}
class OperationResult {}
class ApplyReturnResponseConfirmation {}
