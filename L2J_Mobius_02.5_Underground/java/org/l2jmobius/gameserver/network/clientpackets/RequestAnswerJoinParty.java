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
package org.l2jmobius.gameserver.network.clientpackets;

import org.l2jmobius.gameserver.config.PlayerConfig;
import org.l2jmobius.gameserver.entity.actor.Player;
import org.l2jmobius.gameserver.entity.actor.request.PartyRequest;
import org.l2jmobius.gameserver.entity.groups.Party;
import org.l2jmobius.gameserver.entity.groups.PartyMessageType;
import org.l2jmobius.gameserver.entity.groups.matching.MatchingRoom;
import org.l2jmobius.gameserver.entity.groups.matching.PartyMatchingRoom;
import org.l2jmobius.gameserver.network.SystemMessageId;
import org.l2jmobius.gameserver.network.serverpackets.ExPartyRoomMember;
import org.l2jmobius.gameserver.network.serverpackets.JoinParty;
import org.l2jmobius.gameserver.network.serverpackets.SystemMessage;

/**
 * @author Mobius
 */
public class RequestAnswerJoinParty extends ClientPacket
{
	private int _response;
	
	@Override
	protected void readImpl()
	{
		_response = readInt();
	}
	
	@Override
	protected void runImpl()
	{
		final Player player = getPlayer();
		if (player == null)
		{
			return;
		}
		
		final PartyRequest request = player.getRequest(PartyRequest.class);
		if ((request == null) || request.isProcessing() || !player.removeRequest(request.getClass()))
		{
			return;
		}
		
		request.setProcessing(true);
		
		final Player requestor = request.getPlayer();
		if (requestor == null)
		{
			return;
		}
		
		final Party party = request.getParty();
		final Party requestorParty = requestor.getParty();
		// The requestor joined another party, or this one was disbanded or left while the invite was open.
		if (((requestorParty != null) && (requestorParty != party)) || !party.containsPlayer(requestor))
		{
			return;
		}
		
		requestor.sendPacket(new JoinParty(_response));
		if (_response == 1)
		{
			if (party.getMemberCount() >= PlayerConfig.ALT_PARTY_MAX_MEMBERS)
			{
				final SystemMessage sm = new SystemMessage(SystemMessageId.THE_PARTY_IS_FULL);
				player.sendPacket(sm);
				requestor.sendPacket(sm);
				return;
			}
			
			// Assign the party to the leader upon accept of his partner.
			if (requestorParty == null)
			{
				requestor.setParty(party);
			}
			
			player.joinParty(party);
			
			final MatchingRoom requestorRoom = requestor.getMatchingRoom();
			if (requestorRoom != null)
			{
				// Already seated: only the membership type changed, do not announce the entry again.
				if (player.getMatchingRoom() != requestorRoom)
				{
					requestorRoom.addMember(player);
				}
				else if (requestorRoom instanceof PartyMatchingRoom)
				{
					for (Player member : requestorRoom.getMembers())
					{
						member.sendPacket(new ExPartyRoomMember(member, (PartyMatchingRoom) requestorRoom));
					}
				}
			}
		}
		else if (_response == -1)
		{
			final SystemMessage sm = new SystemMessage(SystemMessageId.C1_IS_SET_TO_REFUSE_PARTY_REQUESTS_AND_CANNOT_RECEIVE_A_PARTY_REQUEST);
			sm.addPcName(player);
			requestor.sendPacket(sm);
			
			if (party.getMemberCount() == 1)
			{
				party.removePartyMember(requestor, PartyMessageType.NONE);
			}
		}
		else if (party.getMemberCount() == 1)
		{
			party.removePartyMember(requestor, PartyMessageType.NONE);
		}
		
		party.setPendingInvitation(false);
		request.setProcessing(false);
	}
}
