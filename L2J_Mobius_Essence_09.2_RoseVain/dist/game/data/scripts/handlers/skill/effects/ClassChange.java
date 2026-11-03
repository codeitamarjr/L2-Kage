/*
 * Copyright (c) 2013 L2jMobius
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
 * IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
package handlers.skill.effects;

import org.l2jmobius.commons.threads.ThreadPool;
import org.l2jmobius.gameserver.data.xml.SkillData;
import org.l2jmobius.gameserver.entity.actor.Creature;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.enums.player.SubclassInfoType;
import org.l2jmobius.gameserver.entity.actor.holders.player.Shortcut;
import org.l2jmobius.gameserver.entity.groups.Party;
import org.l2jmobius.gameserver.entity.item.instance.Item;
import org.l2jmobius.gameserver.mechanics.effects.AbstractEffect;
import org.l2jmobius.gameserver.mechanics.olympiad.OlympiadManager;
import org.l2jmobius.gameserver.mechanics.skill.AbnormalType;
import org.l2jmobius.gameserver.mechanics.skill.BuffInfo;
import org.l2jmobius.gameserver.mechanics.skill.Skill;
import org.l2jmobius.gameserver.mechanics.skill.enums.SkillFinishType;
import org.l2jmobius.gameserver.network.SystemMessageId;
import org.l2jmobius.gameserver.network.serverpackets.AcquireSkillDone;
import org.l2jmobius.gameserver.network.serverpackets.AcquireSkillList;
import org.l2jmobius.gameserver.network.serverpackets.ExSubjobInfo;
import org.l2jmobius.gameserver.network.serverpackets.PartySmallWindowAll;
import org.l2jmobius.gameserver.network.serverpackets.PartySmallWindowDeleteAll;
import org.l2jmobius.gameserver.network.serverpackets.SystemMessage;
import org.l2jmobius.gameserver.taskmanagers.AutoUseTaskManager;
import org.l2jmobius.gameserver.util.StatSet;

/**
 * @author Sdw, Mobius
 */
public class ClassChange extends AbstractEffect
{
	private static final int IDENTITY_CRISIS_SKILL_ID = 1570;
	
	private final int _index;
	
	public ClassChange(StatSet params)
	{
		_index = params.getInt("index", 0);
	}
	
	@Override
	public boolean isInstant()
	{
		return true;
	}
	
	@Override
	public void instant(Creature effector, Creature effected, Skill skill, Item item)
	{
		if (!effected.isPlayer())
		{
			return;
		}
		
		// Executing later otherwise interrupted exception during storeCharBase.
		ThreadPool.schedule(() ->
		{
			final Player player = effected.asPlayer();
			if (player.isTransformed() || player.isSubclassLocked() || player.isAffectedBySkill(IDENTITY_CRISIS_SKILL_ID))
			{
				player.sendMessage("You cannot switch your class right now!");
				return;
			}
			
			final Skill identityCrisis = SkillData.getInstance().getSkill(IDENTITY_CRISIS_SKILL_ID, 1);
			if (identityCrisis != null)
			{
				identityCrisis.applyEffects(player, player);
			}
			
			if (OlympiadManager.getInstance().isRegisteredInComp(player))
			{
				OlympiadManager.getInstance().unRegisterNoble(player);
			}
			
			final int activeClass = player.getPlayerClass().getId();
			player.setActiveClass(_index);
			
			final SystemMessage msg = new SystemMessage(SystemMessageId.YOU_HAVE_SUCCESSFULLY_SWITCHED_S1_TO_S2);
			msg.addClassId(activeClass);
			msg.addClassId(player.getPlayerClass().getId());
			player.sendPacket(msg);
			
			player.broadcastUserInfo();
			player.sendStorageMaxCount();
			
			BuffInfo info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_COMBAT);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_PDEF);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_MDEF);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_PATK);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_MATK);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_PATK);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_STR);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_DEX);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_INT);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_WIT);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_DEX);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			info = player.getEffectList().getFirstBuffInfoByAbnormalType(AbnormalType.STAT_SKILL_CON);
			player.getEffectList().remove(info, SkillFinishType.REMOVED, true, true);
			
			player.sendPacket(new AcquireSkillList(player));
			player.sendPacket(new ExSubjobInfo(player, SubclassInfoType.CLASS_CHANGED));
			player.sendPacket(new AcquireSkillDone());
			
			if (player.isInParty())
			{
				// Delete party window for other party members.
				final Party party = player.getParty();
				party.broadcastToPartyMembers(player, PartySmallWindowDeleteAll.STATIC_PACKET);
				for (Player member : party.getMembers())
				{
					// And re-add
					if (member != player)
					{
						member.sendPacket(new PartySmallWindowAll(member, party));
					}
				}
				
				party.refreshPartyEffectIcons();
			}
			
			// Stop auto use.
			for (Shortcut shortcut : player.getAllShortcuts())
			{
				if (!shortcut.isAutoUse())
				{
					continue;
				}
				
				player.removeAutoShortcut(shortcut.getSlot(), shortcut.getPage());
				
				if (player.getAutoUseSettings().isAutoSkill(shortcut.getId()))
				{
					final Skill knownSkill = player.getKnownSkill(shortcut.getId());
					if (knownSkill != null)
					{
						if (knownSkill.hasNegativeEffect())
						{
							AutoUseTaskManager.getInstance().removeAutoSkill(player, shortcut.getId());
						}
						else
						{
							AutoUseTaskManager.getInstance().removeAutoBuff(player, shortcut.getId());
						}
					}
				}
				else
				{
					final Item knownItem = player.getInventory().getItemByObjectId(shortcut.getId());
					if (knownItem != null)
					{
						if (knownItem.isPotion())
						{
							AutoUseTaskManager.getInstance().removeAutoPotionItem(player);
						}
						else
						{
							AutoUseTaskManager.getInstance().removeAutoSupplyItem(player, knownItem.getId());
						}
					}
				}
			}
			
			// Disarm unusable equipment.
			player.disarmUnusableEquipment();
			
			// Fix Death Knight model animation.
			if (player.isDeathKnight())
			{
				player.transform(101, false);
				ThreadPool.schedule(() -> player.stopTransformation(false), 50);
			}
		}, 500);
	}
}
